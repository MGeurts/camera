<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CameraService
{
    /**
     * How long (seconds) we trust a discovered auth method before re-checking.
     * 24 hours is a safe default — cameras don't change auth scheme at runtime.
     */
    private const AUTH_CACHE_TTL = 86400;

    /**
     * Lazily-loaded, filtered camera list.  Config doesn't change at runtime
     * so we only parse it once per service instance.
     */
    private ?Collection $cameras = null;

    /* ══════════════════════════════════════════════════════════════
       PUBLIC API
    ══════════════════════════════════════════════════════════════ */

    public function all(): Collection
    {
        return $this->cameras ??= collect(config('cameras.cameras'))
            ->filter(fn ($c) => $c['enabled'] ?? true)
            ->values();
    }

    public function find(int $id): ?array
    {
        return $this->all()->firstWhere('id', $id);
    }

    /**
     * Fetch a JPEG snapshot from the camera, or null on failure.
     */
    public function fetchSnapshot(array $camera): ?string
    {
        return $this->request($camera, $this->snapshotUrl($camera))?->body();
    }

    /**
     * Returns true if the camera's snapshot endpoint responds successfully.
     */
    public function isOnline(array $camera): bool
    {
        return $this->fetchSnapshot($camera) !== null;
    }

    /**
     * Check all cameras concurrently and return their online status.
     *
     * Strategy per camera:
     *  - Auth method already known → fire one request with that method.
     *  - Auth method unknown      → fire Basic and Digest simultaneously;
     *                               store the winner for future requests.
     *
     * @return Collection<int, array{id: int, name: string, online: bool, location: string}>
     */
    public function allStatus(): Collection
    {
        $cameras = $this->all();

        if ($cameras->isEmpty()) {
            return collect();
        }

        // Split cameras into those with a known auth method and unknowns.
        $known = $cameras->filter(fn ($c) => $this->cachedAuth($c['id']) !== null);
        $unknown = $cameras->filter(fn ($c) => $this->cachedAuth($c['id']) === null);

        $results = collect();

        // ── Known cameras: one request each, pooled ──────────────────────────
        if ($known->isNotEmpty()) {
            $responses = Http::pool(function ($pool) use ($known) {
                return $known->map(function ($camera) use ($pool) {
                    $authMethod = $this->cachedAuth($camera['id']);

                    $pending = $pool->as("k_{$camera['id']}")
                        ->timeout(3)
                        ->withOptions(['verify' => config('cameras.verify_ssl', false)]);

                    return $authMethod === 'digest'
                        ? $pending->withDigestAuth($camera['username'], $camera['password'])->get($this->snapshotUrl($camera))
                        : $pending->withBasicAuth($camera['username'], $camera['password'])->get($this->snapshotUrl($camera));
                })->all();
            });

            foreach ($known as $camera) {
                $response = $responses["k_{$camera['id']}"] ?? null;
                $online = $response instanceof Response && $response->successful();

                // If the cached method suddenly stopped working, evict the cache
                // so the next poll re-discovers the correct method.
                if (! $online && $response instanceof Response && $response->status() === 401) {
                    $this->forgetAuth($camera['id']);
                    Log::info('Camera auth cache evicted — will re-probe on next poll', [
                        'camera' => $camera['name'],
                        'ip' => $camera['ip'],
                    ]);
                }

                $results->push($this->statusEntry($camera, $online));
            }
        }

        // ── Unknown cameras: Basic + Digest in parallel, pick the winner ─────
        if ($unknown->isNotEmpty()) {
            $responses = Http::pool(function ($pool) use ($unknown) {
                $requests = [];
                foreach ($unknown as $camera) {
                    $opts = $pool->timeout(3)
                        ->withOptions(['verify' => config('cameras.verify_ssl', false)]);

                    $requests[] = $opts->withBasicAuth($camera['username'], $camera['password'])
                        ->as("b_{$camera['id']}")
                        ->get($this->snapshotUrl($camera));

                    $requests[] = $opts->withDigestAuth($camera['username'], $camera['password'])
                        ->as("d_{$camera['id']}")
                        ->get($this->snapshotUrl($camera));
                }

                return $requests;
            });

            foreach ($unknown as $camera) {
                $basicOk = ($responses["b_{$camera['id']}"] ?? null) instanceof Response
                         && $responses["b_{$camera['id']}"]->successful();
                $digestOk = ($responses["d_{$camera['id']}"] ?? null) instanceof Response
                         && $responses["d_{$camera['id']}"]->successful();

                if ($basicOk) {
                    $this->storeAuth($camera['id'], 'basic');
                    Log::debug('Camera auth discovered: basic', ['camera' => $camera['name'], 'ip' => $camera['ip']]);
                } elseif ($digestOk) {
                    $this->storeAuth($camera['id'], 'digest');
                    Log::debug('Camera auth discovered: digest', ['camera' => $camera['name'], 'ip' => $camera['ip']]);
                }

                $results->push($this->statusEntry($camera, $basicOk || $digestOk));
            }
        }

        // Restore the original order from $cameras.
        return $cameras->map(
            fn ($c) => $results->firstWhere('id', $c['id'])
        )->values();
    }

    /**
     * Fetch device info via Hikvision ISAPI /System/deviceInfo.
     * Always returns an array with a 'success' key.
     */
    public function deviceInfo(array $camera): array
    {
        $url = $this->isApiUrl($camera, 'System/deviceInfo');
        $response = $this->request($camera, $url);

        if ($response === null) {
            return [
                'success' => false,
                'status' => 0,
                'reason' => "Connection failed — camera HTTP port {$camera['http_port']} not reachable at {$camera['ip']}",
            ];
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'status' => $response->status(),
                'reason' => match ($response->status()) {
                    401 => 'Authentication failed (HTTP 401) — check username and password in config/cameras.php',
                    403 => 'Access denied (HTTP 403) — the camera account may lack Remote access permission',
                    404 => 'Endpoint not found (HTTP 404) — this camera model may not support ISAPI device info',
                    default => "Camera returned HTTP {$response->status()}",
                },
            ];
        }

        return $this->parseDeviceInfoXml($response->body(), $camera);
    }

    /* ══════════════════════════════════════════════════════════════
       PRIVATE HELPERS
    ══════════════════════════════════════════════════════════════ */

    /**
     * Single HTTP request helper with persistent auth-caching.
     *
     * 1. Try the previously successful auth method (Basic if unknown).
     * 2. On 401 with no cached method, try the other auth and cache the winner.
     * 3. Return null (and log) on network failure or exhausted auth options.
     */
    private function request(array $camera, string $url, int $timeout = 5): ?Response
    {
        $cachedAuth = $this->cachedAuth($camera['id']);
        $primary = $cachedAuth ?? 'basic';
        $fallback = $primary === 'basic' ? 'digest' : 'basic';

        try {
            $response = $this->makeRequest($camera, $url, $primary, $timeout);

            if ($response->successful()) {
                if ($cachedAuth === null) {
                    $this->storeAuth($camera['id'], $primary);
                    Log::debug('Camera auth discovered: basic', ['camera' => $camera['name'], 'ip' => $camera['ip']]);
                }

                return $response;
            }

            // Only try the other method when we have no cached preference and
            // the server is explicitly rejecting our credentials.
            if ($response->status() === 401 && $cachedAuth === null) {
                $response = $this->makeRequest($camera, $url, $fallback, $timeout);

                if ($response->successful()) {
                    $this->storeAuth($camera['id'], $fallback);
                    Log::debug('Camera auth discovered: digest', ['camera' => $camera['name'], 'ip' => $camera['ip']]);

                    return $response;
                }
            }

            // Evict a stale cached method if the camera is now rejecting it.
            if ($response->status() === 401 && $cachedAuth !== null) {
                $this->forgetAuth($camera['id']);
            }

            Log::warning('Camera request failed', [
                'camera' => $camera['name'],
                'ip' => $camera['ip'],
                'url' => $url,
                'status' => $response->status(),
                'auth' => $primary,
            ]);

            return null;

        } catch (\Throwable $e) {
            Log::error('Camera request exception', [
                'camera' => $camera['name'],
                'ip' => $camera['ip'],
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build and fire a single HTTP request with the given auth method.
     */
    private function makeRequest(
        array $camera,
        string $url,
        string $authMethod,
        int $timeout
    ): Response {
        $pending = Http::timeout($timeout)
            ->withOptions(['verify' => config('cameras.verify_ssl', false)]);

        return $authMethod === 'digest'
            ? $pending->withDigestAuth($camera['username'], $camera['password'])->get($url)
            : $pending->withBasicAuth($camera['username'], $camera['password'])->get($url);
    }

    /**
     * Snapshot ISAPI URL for a camera.
     */
    private function snapshotUrl(array $camera): string
    {
        return $this->isApiUrl(
            $camera,
            sprintf('Streaming/channels/%s01/picture', $camera['channel'])
        );
    }

    /**
     * Build any ISAPI URL for a camera.
     */
    private function isApiUrl(array $camera, string $path): string
    {
        return sprintf('http://%s:%s/ISAPI/%s', $camera['ip'], $camera['http_port'], $path);
    }

    /**
     * Build a status result entry.
     *
     * @return array{id: int, name: string, online: bool, location: string}
     */
    private function statusEntry(array $camera, bool $online): array
    {
        return [
            'id' => $camera['id'],
            'name' => $camera['name'],
            'online' => $online,
            'location' => $camera['location'] ?? '',
        ];
    }

    /**
     * Parse the XML body from /ISAPI/System/deviceInfo.
     */
    private function parseDeviceInfoXml(string $body, array $camera): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();

        if ($xml === false) {
            Log::warning('Camera returned invalid XML for deviceInfo', [
                'camera' => $camera['name'],
                'ip' => $camera['ip'],
            ]);

            return [
                'success' => false,
                'status' => 0,
                'reason' => 'Invalid XML returned by camera — firmware may not support this endpoint',
            ];
        }

        return [
            'success' => true,
            'model' => (string) ($xml->model ?? 'Unknown'),
            'serial' => (string) ($xml->serialNumber ?? 'Unknown'),
            'firmware' => (string) ($xml->firmwareVersion ?? 'Unknown'),
            'device_name' => (string) ($xml->deviceName ?? $camera['name']),
        ];
    }

    /* ── Auth cache helpers ───────────────────────────────────────── */

    private function authCacheKey(int $cameraId): string
    {
        return "camera_auth_{$cameraId}";
    }

    /** @return 'basic'|'digest'|null */
    private function cachedAuth(int $cameraId): ?string
    {
        return Cache::get($this->authCacheKey($cameraId));
    }

    private function storeAuth(int $cameraId, string $method): void
    {
        Cache::put($this->authCacheKey($cameraId), $method, self::AUTH_CACHE_TTL);
    }

    private function forgetAuth(int $cameraId): void
    {
        Cache::forget($this->authCacheKey($cameraId));
    }
}

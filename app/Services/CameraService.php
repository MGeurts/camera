<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CameraService
{
    public function all(): Collection
    {
        return collect(config('cameras.cameras'))
            ->filter(fn ($c) => $c['enabled'] ?? true)
            ->values();
    }

    public function find(int $id): ?array
    {
        return $this->all()->firstWhere('id', $id);
    }

    public function fetchSnapshot(array $camera): ?string
    {
        try {
            $url = "http://{$camera['ip']}:{$camera['http_port']}/ISAPI/Streaming/channels/{$camera['channel']}01/picture";

            $response = Http::withBasicAuth($camera['username'], $camera['password'])->timeout(5)->withOptions(['verify' => false])->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            $response = Http::withDigestAuth($camera['username'], $camera['password'])->timeout(5)->withOptions(['verify' => false])->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            Log::warning("Camera snapshot failed [{$camera['name']}]", ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::error("Camera snapshot exception [{$camera['name']}]: ".$e->getMessage());
        }

        return null;
    }

    public function isOnline(array $camera): bool
    {
        // Use the snapshot endpoint as the health check — if snapshots are
        // visible in the browser, this will always return true. The dedicated
        // /ISAPI/System/status endpoint is blocked on many camera models.
        try {
            $url = "http://{$camera['ip']}:{$camera['http_port']}/ISAPI/Streaming/channels/{$camera['channel']}01/picture";

            $res = Http::withBasicAuth($camera['username'], $camera['password'])->timeout(3)->withOptions(['verify' => false])->get($url);

            if ($res->successful()) {
                return true;
            }

            $res = Http::withDigestAuth($camera['username'], $camera['password'])->timeout(3)->withOptions(['verify' => false])->get($url);

            return $res->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Fetch device info via Hikvision ISAPI.
     * Always returns an array with a 'success' key.
     * Tries Basic auth first, then Digest — just like snapshot fetch.
     */
    public function deviceInfo(array $camera): array
    {
        $url = "http://{$camera['ip']}:{$camera['http_port']}/ISAPI/System/deviceInfo";

        try {
            $res = Http::withBasicAuth($camera['username'], $camera['password'])->timeout(5)->withOptions(['verify' => false])->get($url);

            if (! $res->successful()) {
                $res = Http::withDigestAuth($camera['username'], $camera['password'])->timeout(5)->withOptions(['verify' => false])->get($url);
            }

            if ($res->successful()) {
                $xml = simplexml_load_string($res->body());

                return [
                    'success' => true,
                    'model' => (string) ($xml->model ?? 'Unknown'),
                    'serial' => (string) ($xml->serialNumber ?? 'Unknown'),
                    'firmware' => (string) ($xml->firmwareVersion ?? 'Unknown'),
                    'device_name' => (string) ($xml->deviceName ?? $camera['name']),
                ];
            }

            return [
                'success' => false,
                'status' => $res->status(),
                'reason' => match ($res->status()) {
                    401 => 'Authentication failed (HTTP 401) — check username and password in config/cameras.php',
                    403 => 'Access denied (HTTP 403) — the camera account may lack Remote access permission',
                    404 => 'Endpoint not found (HTTP 404) — this camera model may not support ISAPI device info',
                    default => "Camera returned HTTP {$res->status()}",
                },
            ];

        } catch (\Throwable $e) {
            Log::error("Device info [{$camera['name']}]: ".$e->getMessage());
            $msg = $e->getMessage();

            return [
                'success' => false,
                'status' => 0,
                'reason' => str_contains($msg, 'timed out') || str_contains($msg, 'timeout')
                    ? "Connection timed out — camera HTTP port {$camera['http_port']} not reachable at {$camera['ip']}"
                    : "Connection failed — {$msg}",
            ];
        }
    }
}

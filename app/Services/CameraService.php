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

            $response = Http::withBasicAuth($camera['username'], $camera['password'])
                ->timeout(5)->withOptions(['verify' => false])->get($url);
            if ($response->successful()) {
                return $response->body();
            }

            $response = Http::withDigestAuth($camera['username'], $camera['password'])
                ->timeout(5)->withOptions(['verify' => false])->get($url);
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
        try {
            return Http::withBasicAuth($camera['username'], $camera['password'])
                ->timeout(3)->withOptions(['verify' => false])
                ->get("http://{$camera['ip']}:{$camera['http_port']}/ISAPI/System/status")
                ->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function deviceInfo(array $camera): ?array
    {
        try {
            $res = Http::withBasicAuth($camera['username'], $camera['password'])
                ->timeout(5)->withOptions(['verify' => false])
                ->get("http://{$camera['ip']}:{$camera['http_port']}/ISAPI/System/deviceInfo");

            if ($res->successful()) {
                $xml = simplexml_load_string($res->body());

                return [
                    'model' => (string) ($xml->model ?? 'Unknown'),
                    'serial' => (string) ($xml->serialNumber ?? 'Unknown'),
                    'firmware' => (string) ($xml->firmwareVersion ?? 'Unknown'),
                    'device_name' => (string) ($xml->deviceName ?? $camera['name']),
                ];
            }
        } catch (\Throwable $e) {
            Log::error("Device info [{$camera['name']}]: ".$e->getMessage());
        }

        return null;
    }
}

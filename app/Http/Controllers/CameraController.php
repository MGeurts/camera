<?php

namespace App\Http\Controllers;

use App\Services\CameraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CameraController extends Controller
{
    public function __construct(private CameraService $cameras) {}

    public function index()
    {
        $cameras = $this->cameras->all();
        $refresh = config('cameras.snapshot_refresh', 3000);

        return view('cameras.index', compact('cameras', 'refresh'));
    }

    public function show(int $id)
    {
        $camera = $this->cameras->find($id);

        if (! $camera) {
            abort(404, 'Camera not found');
        }
        $refresh = config('cameras.snapshot_refresh', 3000);

        return view('cameras.show', compact('camera', 'refresh'));
    }

    public function snapshot(int $id): Response
    {
        $camera = $this->cameras->find($id);

        if (! $camera) {
            abort(404);
        }

        $image = $this->cameras->fetchSnapshot($camera);

        if (! $image) {
            return response($this->offlineSvg($camera['name']), 503)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        }

        return response($image, 200)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function allStatus(): JsonResponse
    {
        return response()->json($this->cameras->allStatus());
    }

    public function deviceInfo(int $id): JsonResponse
    {
        $camera = $this->cameras->find($id);

        if (! $camera) {
            return response()->json(['success' => false, 'reason' => 'Camera not found in config'], 404);
        }

        $info = $this->cameras->deviceInfo($camera);

        // Return 200 always — let the frontend decide how to render success vs failure
        return response()->json($info);
    }

    private function offlineSvg(string $name): string
    {
        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360">
            <rect width="640" height="360" fill="#0d0f14"/>
            <rect x="1" y="1" width="638" height="358" fill="none" stroke="#e02040" stroke-width="1" stroke-dasharray="8,4" opacity="0.35"/>
            <line x1="0" y1="0" x2="640" y2="360" stroke="#e02040" stroke-width="1" opacity="0.12"/>
            <line x1="640" y1="0" x2="0" y2="360" stroke="#e02040" stroke-width="1" opacity="0.12"/>
            <circle cx="320" cy="150" r="26" fill="none" stroke="#e02040" stroke-width="2" opacity="0.7"/>
            <line x1="301" y1="131" x2="339" y2="169" stroke="#e02040" stroke-width="2" opacity="0.7"/>
            <text x="320" y="210" font-family="monospace" font-size="12" fill="#e02040" text-anchor="middle" opacity="0.85">SIGNAL LOST</text>
            <text x="320" y="234" font-family="monospace" font-size="10" fill="#6070a0" text-anchor="middle">{$name}</text>
            <text x="320" y="255" font-family="monospace" font-size="9" fill="#3a4060" text-anchor="middle">CHECK NETWORK CONNECTION</text>
            </svg>
        SVG;
    }
}

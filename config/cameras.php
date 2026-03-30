<?php

/**
 * Camera configuration
 *
 * Define cameras via .env using the pattern:
 *   CAMERA_{N}_NAME, CAMERA_{N}_IP, CAMERA_{N}_RTSP_PORT, CAMERA_{N}_HTTP_PORT,
 *   CAMERA_{N}_USERNAME, CAMERA_{N}_PASSWORD,
 *   CAMERA_{N}_CHANNEL, CAMERA_{N}_STREAM, CAMERA_{N}_LOCATION, CAMERA_{N}_ENABLED
 *
 * N = 1 .. 24
 *
 * Global defaults:
 *   CAMERA_DEFAULT_USERNAME        fallback username for all cameras
 *   CAMERA_DEFAULT_PASSWORD        fallback password for all cameras
 *   CAMERA_DEFAULT_RTSP_PORT       fallback RTSP port                    (default 554)
 *   CAMERA_DEFAULT_HTTP_PORT       fallback HTTP port                    (default 80)
 *
 * Add your IP      cameras here. Each camera needs:
 * - name:          Display name
 * - ip:            Camera IP address
 * - port:          RTSP port (default 554) and HTTP port (default 80)
 * - username:      Camera username (default: admin)
 * - password:      Camera password
 * - channel:       Channel number (default 1)
 * - stream:        'main' (high quality) or 'sub' (low quality)
 * - location:      'Interior' or 'Exterior' (used for grouping in the UI)
 * - enabled:       true/false (set to false to disable a camera without removing it from the config)
 */
$cameras = [];

$cameras_maximum = 24;

for ($i = 1; $i <= $cameras_maximum; $i++) {
    $host = env("CAMERA_{$i}_IP");

    if (empty($host)) {
        continue;
    }

    $enabled = env("CAMERA_{$i}_ENABLED", 'true');
    if (strtolower((string) $enabled) === 'false' || $enabled === '0') {
        continue;
    }

    $cameras[] = [
        'id' => $i,
        'name' => env("CAMERA_{$i}_NAME", "Camera {$i}"),
        'ip' => $host,
        'rtsp_port' => (int) env("CAMERA_{$i}_RTSP_PORT", env('CAMERA_DEFAULT_RTSP_PORT', 554)),
        'http_port' => (int) env("CAMERA_{$i}_HTTP_PORT", env('CAMERA_DEFAULT_HTTP_PORT', 80)),
        'username' => env("CAMERA_{$i}_USERNAME", env('CAMERA_DEFAULT_USERNAME', 'admin')),
        'password' => env("CAMERA_{$i}_PASSWORD", env('CAMERA_DEFAULT_PASSWORD', '')),
        'channel' => (int) env("CAMERA_{$i}_CHANNEL", 1),
        'stream' => env("CAMERA_{$i}_STREAM", 'sub'),
        'location' => env("CAMERA_{$i}_LOCATION", 'Exterior'),
        'enabled' => $enabled,
    ];
}

return [

    /*
    |--------------------------------------------------------------------------
    | Camera Settings
    |--------------------------------------------------------------------------
    */
    'cameras' => $cameras,

    'snapshot_refresh' => 3000, // milliseconds

];

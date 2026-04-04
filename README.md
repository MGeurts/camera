# 🎥 Hikvision Camera Surveillance Dashboard

![](https://img.shields.io/badge/PHP-8.3-informational?style=flat&logo=php&color=4f5b93)
![](https://img.shields.io/badge/Laravel-13-informational?style=flat&logo=laravel&color=ef3b2d)
![Latest Stable Version](https://img.shields.io/github/release/MGeurts/camera)
[![Donate via PayPal](https://img.shields.io/badge/Donate-PayPal-blue.svg?logo=paypal)](https://www.paypal.me/MGeurtsKREAWEB)
[![Buy Me a Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-orange.svg?logo=buy-me-a-coffee&logoColor=white)](https://buymeacoffee.com/MGeurts)

A clean, professional **Laravel 13** application for monitoring up to 24 Hikvision IP cameras in real time. Features a tactical dark/light-theme UI, live JPEG snapshot cycling, automatic auth discovery, per-camera online/offline tracking, and a fullscreen multi-camera grid.

---

## Screenshots

<img src="https://github.com/MGeurts/camera/blob/main/public/camera.webp" class="rounded" alt="Camera grid dashboard"/>
<img src="https://github.com/MGeurts/camera/blob/main/public/camera-fullscreen.webp" class="rounded" alt="Fullscreen grid"/>
<img src="https://github.com/MGeurts/camera/blob/main/public/camera-single.webp" class="rounded" alt="Single camera view"/>

---

## Features

- **Live snapshot grid** — up to 24 cameras in a 1–6 column layout with auto-sizing
- **Double-buffer rendering** — seamless frame swaps with no flicker
- **Automatic auth discovery** — tries Basic auth first, falls back to Digest; remembers the working method per camera (cached for 24 h)
- **Image-driven status** — ONLINE/OFFLINE badges derived from actual image loads, not a separate API poll; header count always matches
- **Offline duration tracking** — badges show `● OFFLINE 4m` and tick up over time
- **Fullscreen mode** — browser fullscreen with a HUD bar (clock, camera count, LIVE indicator) and a fade-in quit button
- **Dark / light theme** — tactical dark by default, persisted in `localStorage`, applied before CSS loads to prevent flash
- **Configurable refresh rate** — 1 s to 1 min, with toggle and reset
- **Single camera view** — progress bar, pause/resume, save snapshot, ISAPI device info panel
- **Proxy architecture** — camera credentials never reach the browser

---

## Requirements

| Requirement      | Version                    |
| ---------------- | -------------------------- |
| PHP              | 8.3+                       |
| Laravel          | 13.x                       |
| Composer         | 2.x                        |
| Node.js          | 18+                        |
| Hikvision Camera | Any ISAPI-compatible model |

> **Network**: The Laravel server must be on the **same network** as your cameras, or the cameras must be reachable by IP from the server.

---

## Quick Start

```bash
git clone https://github.com/MGeurts/camera.git
cd camera
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

Then open `http://localhost:8000`.

---

## Camera Configuration

All camera settings live in `.env`. There is no database — cameras are defined as environment variables and read at boot by `config/cameras.php`.

### Global defaults (optional)

```dotenv
CAMERA_DEFAULT_USERNAME=admin
CAMERA_DEFAULT_PASSWORD=yourpassword
CAMERA_DEFAULT_HTTP_PORT=80
CAMERA_DEFAULT_RTSP_PORT=554
```

### Per-camera variables

```dotenv
CAMERA_1_NAME="Front Door"
CAMERA_1_IP=192.168.1.101
CAMERA_1_HTTP_PORT=80          # optional — falls back to CAMERA_DEFAULT_HTTP_PORT
CAMERA_1_RTSP_PORT=554         # optional — falls back to CAMERA_DEFAULT_RTSP_PORT
CAMERA_1_USERNAME=admin        # optional — falls back to CAMERA_DEFAULT_USERNAME
CAMERA_1_PASSWORD=secret       # optional — falls back to CAMERA_DEFAULT_PASSWORD
CAMERA_1_CHANNEL=1
CAMERA_1_STREAM=sub            # sub (default, smaller) or main (full quality)
CAMERA_1_LOCATION="Exterior"   # free text, shown in the camera info bar
CAMERA_1_ENABLED=true
```

Repeat for `CAMERA_2_`, `CAMERA_3_`, … up to `CAMERA_24_`. Any camera without an `_IP` variable is ignored.

### SSL verification

By default SSL verification is disabled (cameras typically use self-signed certificates or plain HTTP):

```dotenv
CAMERA_VERIFY_SSL=false   # default — set to true if cameras use valid certificates
```

After editing `.env`, clear the config cache:

```bash
php artisan config:clear
php artisan cache:clear
```

---

## Architecture

### Directory structure

```
app/
  Http/Controllers/
    CameraController.php   — HTTP layer: routes → service → response
  Services/
    CameraService.php      — all camera logic (fetch, auth, status, device info)
config/
  cameras.php              — builds camera list from .env at boot
resources/
  css/
    app.css                — theme tokens, reset, header, shared components
    index.css              — grid page styles (cards, toolbar, fullscreen overlay)
    show.css               — single-camera page styles (feed, sidebar, controls)
  js/
    app.js                 — clock, header count sync, theme toggle (all pages)
    index.js               — grid page: image cycle, status badges, column solver, fullscreen
    show.js                — single-camera page: buffer swap, progress bar, device info
  views/
    layouts/app.blade.php  — shared HTML shell, header, Vite includes
    cameras/index.blade.php — grid page markup
    cameras/show.blade.php  — single-camera page markup
routes/
  web.php                  — all application routes
vite.config.js             — 6 separate entry points (3 CSS + 3 JS)
```

### How snapshots work

```
Browser  →  GET /cameras/{id}/snapshot
         →  CameraService::fetchSnapshot()
         →  GET http://{ip}:{port}/ISAPI/Streaming/channels/{channel}01/picture
         →  JPEG returned to browser
```

Laravel acts as a proxy. Camera credentials never reach the browser and there are no CORS issues.

### Auth discovery

`CameraService` tries Basic auth first, then Digest on a 401 response. The working method is stored in Laravel's cache (`camera_auth_{id}`) for 24 hours, so subsequent requests go straight to the correct method without an extra round-trip.

### Status tracking

There is no separate status API poll running in the browser. The image cycle itself drives status:

- `onload` success → `markOnline(id)` — a loaded frame proves the camera is reachable
- `onerror` → lightweight `HEAD` request to confirm → `markOffline(id)` if also fails

This means status badges and the header count always reflect exactly what you can see on screen, with zero extra server load.

---

## Routes

| Method | URL                      | Description                      |
| ------ | ------------------------ | -------------------------------- |
| GET    | `/`                      | Camera grid dashboard            |
| GET    | `/cameras/{id}`          | Single camera view               |
| GET    | `/cameras/{id}/snapshot` | Proxied JPEG snapshot            |
| GET    | `/api/cameras/status`    | All cameras online status (JSON) |
| GET    | `/api/cameras/{id}/info` | Hikvision ISAPI device info      |

---

## Troubleshooting

### Camera shows "SIGNAL LOST"

1. **Verify IP and port** — open `http://CAMERA_IP` in a browser on the same network.
2. **Check credentials** — log into the camera web UI to confirm username and password.
3. **Enable HTTP access** — in the camera's Network settings, confirm HTTP is enabled (not HTTPS-only).
4. **Check firewall** — the Laravel server must reach port 80 (or your `HTTP_PORT`) on the camera.
5. **Auth type** — the service tries Basic then Digest automatically. If both fail, a `401` appears in the Laravel log.

### Getting 401 Unauthorized in the log

- Confirm the username and password in `.env` match the camera web UI.
- The camera user needs **Remote: Live View** permission (Configuration → User Management).

### Slow snapshots / high latency

- Use `CAMERA_N_STREAM=sub` — substream produces smaller images and responds faster.
- Reduce the number of concurrent cameras or increase the refresh interval.

### Config changes not taking effect

```bash
php artisan config:clear
php artisan cache:clear
```

The auth discovery cache is separate — to force re-discovery of all cameras:

```bash
php artisan cache:clear
```

### Development mode

Run all services concurrently with:

```bash
composer dev
```

This starts `php artisan serve`, the queue worker, and `npm run dev` (Vite HMR) together.

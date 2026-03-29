# 🎥 Hikvision Surveillance Dashboard

A clean, professional **Laravel 13** application for monitoring your (Hikvision) IP cameras in real time.
Features a tactical light- or dark-theme UI with live MJPEG snapshot streaming, auto-refresh, multi-camera grid layouts, and a fullscreen single-camera view.

---

## Screenshots

<img src="https://github.com/MGeurts/camera/blob/main/public/camera.webp" class="rounded" alt="Camera"/>

The dashboard shows all cameras in a configurable grid (1×, 2×, 3×, 4×, 5x or 6x columns) with:

- Live snapshot previews that auto-refresh every N seconds
- Per-camera online/offline status badges
- HUD overlay with camera ID and timestamp
- Fullscreen single-camera view with device info panel

---

## Requirements

| Requirement      | Version                    |
| ---------------- | -------------------------- |
| PHP              | 8.2+                       |
| Laravel          | 13.x                       |
| Composer         | 2.x                        |
| Hikvision Camera | Any ISAPI-compatible model |

> **Network**: The Laravel server must be on the **same network** as your cameras (or the cameras must be accessible by IP from the server).

---

## Quick Start

### 1. Configure your cameras

Copy `.env.example` to `.env` and fill in your camera details:

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```dotenv
CAMERA_1_NAME="Camera 1"
CAMERA_1_IP=192.168.1.101
CAMERA_1_RTSP_PORT=554
CAMERA_1_HTTP_PORT=80
CAMERA_1_USERNAME=admin
CAMERA_1_PASSWORD=yourpasswordhere
CAMERA_1_CHANNEL=1
CAMERA_1_STREAM=sub
CAMERA_1_LOCATION="Entrance"
CAMERA_1_ENABLED=true

CAMERA_2_NAME="Camera 1"
CAMERA_2_IP=192.168.1.101
# etc...
```

You can add up to (maximum) 24 cameras.

---

## How It Works

### Snapshot Proxy

The app uses Laravel as a **proxy** for camera snapshots:

```
Browser  →  GET /cameras/1/snapshot  →  Laravel  →  Hikvision ISAPI  →  JPEG image
```

This means:

- ✅ Camera credentials are **never exposed** to the browser
- ✅ Works across different networks / VLANs
- ✅ No CORS issues

### Hikvision ISAPI Endpoint Used

```
GET http://{ip}:{port}/ISAPI/Streaming/channels/{channel}01/picture
Authorization: Basic / Digest (tried automatically)
```

This is the standard Hikvision snapshot endpoint supported by all DS-2CD, DS-2DE, and similar models.

---

## Routes

| Method | URL                        | Description                      |
| ------ | -------------------------- | -------------------------------- |
| GET    | `/`                        | Camera grid dashboard            |
| GET    | `/cameras/{id}`            | Single camera fullscreen view    |
| GET    | `/cameras/{id}/snapshot`   | Proxied JPEG snapshot            |
| GET    | `/api/cameras/status`      | All cameras online status (JSON) |
| GET    | `/api/cameras/{id}/status` | Single camera status (JSON)      |
| GET    | `/api/cameras/{id}/info`   | Hikvision device info (JSON)     |

---

## Troubleshooting

### Camera shows "SIGNAL LOST"

1. **Verify IP and port** — try accessing `http://CAMERA_IP` in your browser on the same network.
2. **Check credentials** — log into the camera web UI to confirm username/password.
3. **Enable HTTP access** — in the camera's Network settings, ensure HTTP is enabled (not HTTPS-only).
4. **Check firewall** — the server running Laravel must be able to reach port 80 on the camera.
5. **Try Digest auth** — some newer Hikvision models require Digest authentication. The service tries both Basic and Digest automatically.

### Getting a 401 Unauthorized

- Ensure the username and password in `.env` are correct.
- Check the camera's user management: the account needs "Remote: Live View" permission.

### Slow snapshots / high latency

- Use `stream: 'sub'` (substream) in camera config — it produces smaller images.

### php artisan config:cache issues

After editing `config/cameras.php` or `.env`, clear the cache:

```bash
php artisan config:clear
php artisan cache:clear
```

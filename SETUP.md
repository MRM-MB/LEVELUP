# LevelUp Setup Guide

This document consolidates everything required to run LevelUp locally, in Docker, and with the optional Pico W display. Use it as the single source of truth for ports, environment variables, and testing commands.

## Quick Reference

| Workflow | Commands | Default Ports |
| --- | --- | --- |
| Local PHP + Vite | `npm run dev` & `php artisan serve` | 5173 (Vite), 8000 (Laravel), 8100 (desk simulator) |
| Docker Compose | `docker compose up -d` | 8080 (Nginx proxy to Laravel), 5173 (Vite), 3307 (MySQL), 8000 (desk simulator container) |
| Pico W | `hardware/pico-w/main.py` (Micropython) | Connects to `http://<laptop-ip>:8000/api/pico/display` |

Why two simulator ports? Locally we keep Laravel on `php artisan serve` port 8000, so the standalone Python simulator uses 8100 to avoid conflicts. Inside Docker the web server is exposed via Nginx on host port 8080, leaving host port 8000 free for the simulator container. There is no 8001 variant—stick to the values above unless you intentionally change them.

## Processes & Ports to Keep Alive

Each workflow relies on multiple listeners—keep every process below running in its own terminal/tab so hot reload, API calls, and simulator commands all stay in sync.

| Mode | Service | Command | Host Port | Notes |
| --- | --- | --- | --- | --- |
| Local | Vite dev server | `npm run dev` | 5173 | Provides hot module reload + assets; leave running. |
| Local | Laravel HTTP server | `php artisan serve` | 8000 | Handles API/UI requests; match `APP_URL`. |
| Local | wifi2ble simulator | `python simulator/main.py --port 8100` | 8100 | Picked to stay clear of Laravel’s 8000 listener. |
| Docker | Nginx + Laravel | `docker compose up -d` | 8080 (proxy) | Host traffic hits 8080; PHP-FPM still listens on 8000 inside the container. |
| Docker | Vite from container | `docker compose exec app npm run dev -- --host 0.0.0.0` | 5173 | Enables HMR when developing via Docker; keep attached. |
| Docker | wifi2ble simulator container | Part of `docker compose up -d` | 8000 | API exposed on host: `http://localhost:8000/api/v2/<api_key>/desks`. |
| Any | MySQL (Docker) | `docker compose up -d` | 3307 | Optional exposure if you need a SQL client from host. |

## Prerequisites

- PHP 8.2+ with Composer (ensure `extension=gd` is enabled in `php.ini` for image manipulation and tests)
- Node.js 18+ and npm
- MySQL/MariaDB (XAMPP works fine) or Docker Desktop if you prefer containers
- Python 3.10+ (only if you plan to run the wifi2ble simulator locally)
- Optional: Chrome/Chromium for Laravel Dusk to run tests, MicroPython tooling (e.g., `Thonny`) for the Pico W board

## Local Laravel Workflow

1. **Clone & install dependencies**

   ```powershell
   git clone https://github.com/Lara-Ghi/LevelUp.git
   cd LevelUp/LevelUp_App
   composer install
   npm install
   ```

2. **Environment file & key**

   ```powershell
   Copy-Item .env.example .env -Force
   php artisan key:generate
   ```

   Update `.env` with your database credentials and set the simulator address:

   ```env
   WIFI2BLE_BASE_URL=http://127.0.0.1:8100
   WIFI2BLE_API_KEY=E9Y2LxT4g1hQZ7aD8nR3mWx5P0qK6pV7
   ```

3. **Database**

   ```powershell
   php artisan migrate --seed
   # Optional hard reset
   php artisan migrate:fresh --seed
   ```

4. **Run services (two terminals)**

   ```powershell
   # Terminal 1 - asset dev server
   npm run dev

   # Terminal 2 - Laravel HTTP server without Pico W board
   php artisan serve # Shows app's hotspot

      # Pico W addition - expose Laravel to the hotspot
      php artisan serve --host=0.0.0.0 --port=8000
   ```

   Visit `http://127.0.0.1:8000` once both are running.

   The Pico W connects over your hotspot, so it must see the Laravel server on your laptop’s wireless interface. The plain `php artisan serve` binds to `127.0.0.1` only, which blocks remote devices; adding `--host=0.0.0.0` listens on every interface while keeping the same port 8000.

5. **wifi2ble simulator (outside Docker)**

   ```powershell
   cd ..\wifi2ble-box-simulator-main
   python simulator/main.py --port 8100
   ```

   Keep this third window open alongside your Vite and Laravel terminals. The API is available at `http://127.0.0.1:8100/api/v2/<api_key>/desks`.

## Monitor Simulator Logs

Use the application log to watch every command the app sends to the wifi2ble API along with each response. Run these from the `LevelUp_App` directory:

- **Local PowerShell**

   ```powershell
   Get-Content .\storage\logs\laravel.log -Tail 100 -Wait
   ```

   This streams new entries in real time, so you can see `DeskSimulatorController` payloads and Pico phase updates as they happen.

- **Docker container**

   ```powershell
   docker compose exec app tail -f storage/logs/laravel.log
   ```

   Add `| grep DeskSimulator` if you only want desk-related lines (use `Select-String` on PowerShell if `grep` is unavailable).

## Docker Workflow

1. **Bootstrap env & containers**

   ```powershell
   cd LevelUp/LevelUp_App
   Copy-Item .env.docker.example .env.docker -Force
   docker compose up --build -d
   ```

2. **Install & configure inside the PHP container**

   ```powershell
   docker compose exec app composer install
   docker compose exec app npm install
   docker compose exec app cp .env.docker .env
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --seed
   ```

3. **Daily commands**

   ```powershell
   docker compose up -d
   docker compose exec app npm run dev -- --host 0.0.0.0
   docker compose exec app php artisan test --testsuite=Feature
   docker compose down  # when finished
   ```

4. **Ports & URLs**

   - App: `http://localhost:8080`
   - Vite dev server: `http://localhost:5173`
   - MySQL: `localhost:3307`
   - Simulator API: `http://localhost:8000/api/v2/<api_key>/desks`
   - Laravel (php-fpm) still listens on port 8000 inside the container; Nginx forwards it to host port 8080 so there is no 8001 variant
   - The wifi2ble simulator container exposes port 8000 on the host automatically; you only need the standalone Python simulator when developing outside Docker

## Tests

- Feature suite (Pest):

  ```powershell
  php artisan test --testsuite=Feature
  ```

- Unit suite (Pest):

  ```powershell
  php artisan test --testsuite=Unit
  ```

- UI suite (Laravel Dusk):

  ```powershell
  php artisan dusk --ansi
  ```

   Prerequisites for UI tests:

  - Install Chrome or Chromium (matching versions) on the machine running the tests
  - Run `php artisan dusk:install` once per environment; update `.env.dusk.local` so `APP_URL` matches either `http://127.0.0.1:8000` (local) or `http://localhost:8080` (Docker)
  - Keep ChromeDriver in sync by running `php artisan dusk:chrome-driver --detect` if versions drift
  - Inside Docker use `docker compose exec app php artisan dusk` so the tests run next to the app container

When using Docker, prepend commands with `docker compose exec app`.

## Pico W Display (Optional Hardware)

1. **Configure Wi-Fi and API endpoint**

   - Do **not** edit `hardware/pico-w/config.py` directly (it contains defaults).
   - Create a new file named `hardware/pico-w/secrets.py` (this file is ignored by git).
   - Add your specific configuration to `secrets.py`:
     ```python
     WIFI_SSID = "MyWiFi"
     WIFI_PASSWORD = "password"
     API_URL = "http://<laptop-ip>:8000/api/pico/display"
     ```
   - Run `ipconfig` (Windows) to find your IPv4 address to use in `API_URL`.

2. **Flash files to the board with Thonny**

   1. Install [Thonny](https://thonny.org/) and choose **MicroPython (Raspberry Pi Pico)** as the interpreter.
   2. Connect the Pico W while holding the **BOOTSEL** button so it appears as a USB drive.
   3. In Thonny, open and save the following files to the Raspberry Pi Pico:
      - `hardware/pico-w/main.py`
      - `hardware/pico-w/ssd1306.py`
      - `hardware/pico-w/config.py`
      - `hardware/pico-w/secrets.py` (The one you just created)
   4. Reboot the Pico W (or press `Ctrl+D` in Thonny) to start the script.

3. **Usage**

   - Power the Pico W while your laptop serves the Laravel API (either locally or via Docker with port 8000 accessible on the hotspot network)
   - When running locally, launch Laravel with `php artisan serve --host=0.0.0.0 --port=8000` so the HTTP server is reachable from the Pico over Wi-Fi
   - Ensure your laptop’s firewall allows inbound HTTP on **port 8000 only** over the phone hotspot so the Pico can hit `http://<laptop-ip>:8000/api/pico/display`; no other port range is required
   - The OLED and RGB LED mirror the current sitting/standing phase and pause state logged by the app; the potentiometer controls LED intensity and the pause button maps to the in-app pause toggle

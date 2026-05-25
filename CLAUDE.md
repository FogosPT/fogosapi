# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

FogosPT API — a Portuguese wildfire incident tracking API built on **Laravel 12** (PHP 8.2+). It ingests data from ANEPC (Portugal's national civil protection authority), ICNF (national forestry authority), and IPMA (weather), then exposes that data via REST endpoints. Background jobs handle all data ingestion and social media notifications (Twitter/X, Telegram, Discord, Facebook, Bluesky).

## Commands

```bash
# Install dependencies
composer install

# Copy and configure environment
cp .env.example .env

# Run all tests
./vendor/bin/phpunit

# Run a single test file
./vendor/bin/phpunit tests/Unit/Models/IncidentModelTest.php

# Run a specific test method
./vendor/bin/phpunit --filter testMethodName

# Lint / fix code style (php-cs-fixer)
./vendor/bin/php-cs-fixer fix

# Run the scheduler (requires SCHEDULER_ENABLE=true in .env)
php artisan schedule:run

# Dispatch a job manually via artisan
php artisan tinker

# List available artisan commands
php artisan list

# Import IPMA climate normals (one-shot, required before wave detection works)
php artisan weather:import-normals
php artisan weather:import-normals --period=1991-2020
php artisan weather:import-normals --period=1971-2000
```

## Architecture

### Framework & Infrastructure
- **Laravel 12** (PHP 8.2+). App bootstrap lives in `bootstrap/app.php` using the `Application::configure()` style (routes, middleware, schedule, console commands all wired there).
- **MongoDB** (primary datastore via `mongodb/laravel-mongodb` ^5.0) — collection `fires.data` is the main incidents collection
- **Redis** — queue backend (`QUEUE_CONNECTION=redis`). Jobs are dispatched via `dispatch(new JobClass())` and consumed by a queue worker
- **MinIO** (S3-compatible) — object storage for user-submitted incident photos. Configured as the `minio` disk in `config/filesystems.php`. In production, data is bind-mounted at `/data`.
- **Sentry** — error tracking

### Request Lifecycle
All routes are defined in `routes/web.php`. The app has two versioned API families:
- `v1/*` — legacy endpoints served by `LegacyController`, mostly reading from MongoDB with direct queries
- `v2/*` — current endpoints: `IncidentController`, `IncidentPhotoController`, `PhotoModerationController`, `WeatherController`, `RCMController`, `PlanesController`, `WarningsController`, `StatsController`, `OtherController`

Write endpoints (`POST /v2/incidents/{id}/posit`, `POST /v2/incidents/{id}/kml`) are protected by an `API_WRITE_KEY` header check (validated inline in the controller, not via middleware).

Photo endpoints:
- `POST /v2/incidents/{id}/photos` — public upload, rate-limited via `photo.ratelimit` middleware
- `GET /v2/incidents/{id}/photos` — public listing of approved photos (includes GPS coordinates and heading/azimuth)
- `GET|POST /v2/moderation/photos[/...]` — moderation queue, protected by `photo.modauth` middleware checking `PHOTO_MODERATION_KEY` header

Weather extremes:
- `GET /v2/weather/waves` — heat/cold wave detector (WMO 6-day rule). Reads `TemperatureWave` rows where `ongoing=true`, joined with `WeatherStation` for naming. Response is cached in Redis for 1h. See `docs/weather/paths/v2-weather-waves.yaml` for the schema.

### Jobs (Background Processing)
The scheduler runs when `SCHEDULER_ENABLE=true`. Key jobs and their cadence:
- `ProcessANPCAllDataV2` — every 2 minutes; core ingestion loop that fetches incidents from `ANEPC_API_URL` and upserts into MongoDB. After each run it dispatches `CheckIsActive` and `CheckImportantFireIncident`
- `ProcessICNFNewFireData` — every 5 minutes; fetches ICNF fire data
- `UpdateICNFData` — runs at several intervals (4h, 12h, daily, etc.) indexed by a numeric mode argument (0–6)
- `UpdateWeatherData` — hourly; updates weather station readings
- `UpdateWeatherDataDaily` — daily; aggregates daily weather summaries
- `ProcessRCM` — hourly + daily twice; fetches fire danger (Risco de Combustão de Materiais) maps
- `HourlySummary` / `DailySummary` — summary notifications
- `HandleWeatherWarnings` — every 15 minutes; processes IPMA weather warnings
- `DetectTemperatureWaves` — daily at 05:00 (after `UpdateWeatherDataDaily`); applies the WMO 6-day rule to detect heat waves (vs 1991-2020 normals) and cold waves (vs 1971-2000 normals) per station, persists `TemperatureWave` rows, and posts a Discord message on the first detection of each event

### Models (MongoDB)
All models extend `MongoDB\Laravel\Eloquent\Model`. Key models:
- `Incident` — collection `data`; central entity. Has scopes: `isActive()`, `isFire()`, `isFMA()`, `isOtherFire()`, `whereFireId()` (handles both legacy `id` field and new `_id`). Classification constants (`NATUREZA_CODE_*`) and `STATUS_ID`/`STATUS_COLORS` are defined here. Primary key is `_id`.
- `IncidentHistory` / `IncidentStatusHistory` — historical records linked to `Incident` via `id` field
- `IncidentPhoto` — collection `incident_photos`; user-submitted photos with moderation status (`pending`/`approved`). Stores GPS, EXIF, and storage key into the MinIO `incident-photos` bucket.
- `Location` — geocoding lookup table (level 1 = district, level 2 = concelho)
- `WeatherStation` / `WeatherData` / `WeatherDataDaily` — weather data
- `WeatherNormal` — collection `weatherNormals`; monthly mean tmax/tmin per station, indexed by `(stationId, period)`. Populated by the `weather:import-normals` command, which scrapes the IPMA normals pages (`allstations` JS literal) — no PDF parsing.
- `TemperatureWave` — collection `temperatureWaves`; detected heat/cold wave events. `(stationId, type, start_date)` is the upsert key; `ongoing` flags events whose window includes today/yesterday. Read by `GET /v2/weather/waves`.
- `RCM` / `RCMForJS` — fire risk maps
- `Warning` / `WarningMadeira` / `WarningSite` / `WeatherWarning` — various alert types

### Tools (`app/Tools/`)
Stateless helper classes for external integrations:
- `DiscordTool` — `postError()` used throughout for ops alerting
- `TwitterTool` / `TwitterToolV2` — tweet posting
- `TelegramTool`, `FacebookTool`, `BlueskyTool` — social media
- `NotificationTool` — Firebase push notifications
- `ScreenShotTool` — takes headless browser screenshots for social posts
- `HashTagTool` — generates Portuguese location hashtags
- `RCMTool` — processes RCM image data
- `PhotoStorageTool` — uploads/deletes incident photos on the MinIO disk; computes public URLs from `MINIO_PUBLIC_BASE_URL`
- `ImageProcessingTool` — extracts EXIF from PNG `eXIf` chunks (via `lsolesen/pel`), strips metadata, resizes and re-encodes to JPEG via Imagick

### Resources
API responses use resource classes in `app/Resources/` (note: this project keeps them at `app/Resources/`, not the Laravel-default `app/Http/Resources/`). `IncidentResource` shapes the main incident payload; `IncidentPhotoResource` and `IncidentPhotoModerationResource` shape the public and moderator photo payloads respectively.

### API Documentation
OpenAPI 3.0 spec lives in `docs/api.yaml` with referenced path/model fragments under `docs/{fires,weather,rcm,...}/paths` and `docs/.../models`. When adding a new endpoint:
1. Create the path file under the relevant group's `paths/` folder.
2. Reference it from `docs/api.yaml` under the matching section comment.
3. Reuse shared response fragments under `docs/common/responses/` (`401.yaml`, `404.yaml`, `422.yaml`, `500.yaml`).

### Feature Flags (env)
Several features are toggled via `.env`:
- `SCHEDULER_ENABLE` — enable cron jobs
- `LEGACY_ENABLE` — enable v1 endpoints
- `TELEGRAM_ENABLE`, `TWITTER_ENABLE`, `FACEBOOK_ENABLE`, `DISCORD_ENABLE`, `NOTIFICATIONS_ENABLE` — social/notification channels
- `TROLL_MODE` — returns fake data for unauthorized API consumers (checks against whitelisted user-agents/referers)
- `PROXY_ENABLE` / `PROXY_URL` — route outbound requests through a proxy
- `PHOTO_MODERATION_KEY` — header token guarding the photo moderation endpoints
- `PHOTO_UPLOAD_MAX_BYTES`, `PHOTO_RATE_PER_IP_PER_MINUTE`, `PHOTO_RATE_PER_INCIDENT_PER_IP_PER_HOUR`, `PHOTO_RATE_PER_INCIDENT_GLOBAL_PER_HOUR`, `PHOTO_PENDING_PER_INCIDENT_CAP` — photo upload limits and rate gates
- `MINIO_ENDPOINT`, `MINIO_PUBLIC_BASE_URL`, `MINIO_BUCKET`, `MINIO_REGION`, `MINIO_ROOT_USER`, `MINIO_ROOT_PASSWORD` — MinIO/S3 storage configuration

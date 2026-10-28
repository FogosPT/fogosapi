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

# Collapse historical duplicate weather warnings (idempotent; --dry-run to preview)
php artisan weather:collapse-warnings --dry-run
php artisan weather:collapse-warnings
```

## Deploy (production, docker-compose v1 on the host)

The repo is bind-mounted into every container (`/opt/fogosapi` → `/var/www/html`); `vendor/` lives on the host. The prod image has no composer and php-fpm runs opcache with `validate_timestamps=0`, so **code changes are invisible until php-fpm reloads**.

```bash
git pull
# only when composer.lock changed (prod image has no composer):
docker run --rm -v "$PWD":/app -w /app composer:2 install --no-dev --no-interaction --prefer-dist \
  --optimize-autoloader --no-scripts --ignore-platform-req='ext-*'
docker exec -u www-data fogosapi_fogos.api_1 php artisan package:discover   # after composer install
docker exec -u www-data fogosapi_fogos.api_1 php artisan migrate --force     # when there are migrations
docker-compose kill -s USR2 fogos.api        # php-fpm reload + opcache reset; sub-second blip vs seconds of 502s on restart
docker exec -u www-data fogosapi_fogos.api_1 php artisan queue:restart       # workers pick up new code
```

- Run artisan as `-u www-data`: files root creates in `storage/` (e.g. today's log) are not writable by the www-data workers. The API container's entrypoint re-chowns `storage/` on start.
- `docker-compose exec -T` (v1) can swallow output; prefer `docker exec <container>`.
- Image changes (`.docker/**`) are built by the *build docker images* workflow; then `docker-compose pull --ignore-pull-failures && docker-compose up -d`. Pin with `FOGOS_IMAGE_TAG=sha-<8 chars>` in `.env` for reproducible deploys/rollbacks.
- Redis has no published port: the app must use `REDIS_HOST=fogos.redis` and `REDIS_PASSWORD` (compose refuses to start Redis without it).
- `minio/minio` can no longer be pulled (MinIO stopped publishing images); the host keeps the only copy — never `docker image prune -a`.
- Memory limits under `deploy:` only apply with `docker-compose --compatibility up -d`.


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
- `ProcessOcorrenciasSite` — every 2 minutes; core ANEPC ingestion from the ArcGIS `OcorrenciasSite` feed, upserts into MongoDB (`source=anepc`). After each run it dispatches `CheckIsActive` and `CheckImportantFireIncident`. `ProcessANPCAllDataV2` (`ANEPC_API_URL`) is the older feed, no longer scheduled (manual via `SyncANEPCIncidents`).
- `ProcessICNFNewFireData` — every 5 minutes; fetches ICNF fire data (`App\Tools\ICNFTool`), creates fires ANEPC does not have (`source=icnf`)
- **ANEPC ↔ ICNF failover** — `App\Support\AnepcFeedHealth` tracks the newest `DataDosDados` of the ANEPC feed in Redis. ANEPC is primary; when no new data arrives for `ANEPC_STALE_MINUTES` (default 30) the feed is *stale*: `ProcessOcorrenciasSite` stops writing the frozen snapshot, `CheckIsActive` is skipped, and `ProcessICNFNewFireData` owns the `active` flag (ICNF-only fires: active per `ICNFTool::isActive`; ANEPC fires: closed when ICNF says Extinto, never reopened). While ANEPC is fresh the ICNF job only closes Extinto fires and never reactivates, so it does not fight `CheckIsActive`. The stale guard in `CheckIsActive` only applies when dispatched with `guardAnepcFeedHealth: true` (OcorrenciasSite), not for `ProcessANPCAllDataV2`. When fresh ANEPC data returns, ANEPC overwrites the incidents and `CheckIsActive` deactivates anything not in its list. Transitions are posted to Discord.
- `UpdateICNFData` — runs at several intervals (4h, 12h, daily, etc.) indexed by a numeric mode argument (0–6)
- `UpdateWeatherData` — hourly; updates weather station readings
- `UpdateWeatherDataDaily` — daily; aggregates daily weather summaries
- `ProcessRCM` — hourly + daily twice; fetches fire danger (Risco de Combustão de Materiais) maps
- `HourlySummary` / `DailySummary` — summary notifications
- `HandleWeatherWarnings` — every 15 minutes; scrapes IPMA warnings from the homepage and upserts into `weather_warnings` keyed on `(district, type, level)` — one row per active event, updated in place when IPMA republishes with a newer `reportDate`. Use `weather:collapse-warnings` to clean up rows created before the upsert switch.
- `DetectTemperatureWaves` — daily at 05:00 (after `UpdateWeatherDataDaily`); applies the WMO 6-day rule to detect heat waves (vs 1991-2020 normals) and cold waves (vs 1971-2000 normals) per station, persists `TemperatureWave` rows, and posts a Discord message on the first detection of each event

### Seismic (sismos.pt)
Earthquakes for the sismos.pt site (`v2/seismic/*`, `SeismicController`). IPMA's seismic feed only keeps ~30 days, so we archive it.
- `app/Tools/Seismic/*` — one `SeismicSource` per catalogue (`IpmaSeismicTool` areas 3+7, `EmscTool`, `UsgsTool`, `GeofonTool`, `IgnTool`), each parsing into a `SeismicEvent` DTO and dropping events outside `SeismicSource::BBOX`. IPMA times are UTC; `magnitud: "-99.0"` means not yet revised (stored as `magnitude=null`, `provisional=true`).
- `App\Support\EarthquakeMerger` — one `Earthquake` per real event: match by `sources.{src}.id`, else by origin within 10 s / 30 km (doubled for M5+). Preferred solution follows `SeismicSources::PRECEDENCE_PT` (IPMA first) inside PT regions, `PRECEDENCE_OTHER` outside; magnitude from the first source with a final value; place name from IPMA when present. Failover is implicit (no IPMA report → the next source leads).
- `App\Support\SeismicRegion` — `continente|acores|madeira|outro` by coarse polygons.
- `App\Support\SeismicFeedHealth` — per-source freshness in cache, Discord on IPMA/EMSC outages, `primary()` for the site.
- `ProcessSeismicSource($source)` — scheduled per source when `SEISMIC_ENABLE=true` (IPMA/EMSC 2 min, USGS 10 min, GEOFON/IGN 15 min). `php artisan seismic:sync [source] [--days=]` runs it synchronously.
- History: `php artisan seismic:backfill {isc|emsc|usgs} --from=YYYY [--to=YYYY] [--minmag=] [--chunk=month|year] [--sleep=1]` — chunked, retried, idempotent (through the merger); a chunk that returns a full page (`RangeSource::pageLimit()`) is split in two. ISC (`IscTool`, drops events without magnitude) is ~10× more complete than EMSC (it includes IPMA's small events) and is published up to a few months ago, so the intended split is ISC 1900→(now − 3 months), EMSC only for the gap. ISC is slow (~1 min per recent year).
- Alerts: after each merge `ProcessSeismicSource` dispatches `HandleSignificantEarthquake` when `EarthquakeAlert::pending()` says so — final magnitude ≥ `SEISMIC_ALERT_MIN_MAGNITUDE` (3.0) in a PT region (outside PT only if felt), origin < 6 h old (never backfill). The job claims the alert atomically (`notifiedAt` / `notifiedMagnitude` / `notifiedFelt`) and posts once, plus a revision when the magnitude moves ≥ 0.3 or IPMA adds the felt report. Channel: `DiscordTool::postSeismic` on its own webhook (`SEISMIC_DISCORD_WEBHOOK_*`), behind `SEISMIC_NOTIFICATIONS_ENABLE`. `EarthquakeAlert::message()` is channel-agnostic, ready for sismos.pt's own Telegram/Bluesky/X accounts (deliberately not the Fogos.pt ones).
- Statistics: `App\Support\SeismicStats` (pure math: Mc by max curvature +0.2, Aki–Utsu b + Shi–Bolt σ + bootstrap, return periods, Poisson tail, Gardner–Knopoff declustering, Omori p) and `SeismicStatsBuilder`, which streams each region once and writes `seismic_stats` (regions `pt|continente|acores|madeira` × windows `7d|30d|1y|10y|all`). Scheduled as `seismic:stats` hourly at :07 in the scheduler container (`runInBackground`), not on a queue worker. Served by `GET v2/seismic/stats`; `stats/gutenberg-richter` and `stats/heatmap` compute on demand (1 h cache). Source latency stats only count events first seen < 12 h after origin, so backfilled events don't skew them.

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

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

FogosPT API — a Portuguese wildfire incident tracking API built on **Laravel Lumen 8** (PHP 8.0+). It ingests data from ANEPC (Portugal's national civil protection authority), ICNF (national forestry authority), and IPMA (weather), then exposes that data via REST endpoints. Background jobs handle all data ingestion and social media notifications (Twitter/X, Telegram, Discord, Facebook, Bluesky).

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
```

## Architecture

### Framework & Infrastructure
- **Lumen 8** (micro-framework, no session/view layer)
- **MongoDB** (primary datastore via `jenssegers/mongodb`) — collection `fires.data` is the main incidents collection
- **Redis** — queue backend (`QUEUE_CONNECTION=redis`). Jobs are dispatched via `dispatch(new JobClass())` and consumed by a queue worker
- **Sentry** — error tracking

### Request Lifecycle
All routes are defined in `routes/web.php`. The app has two versioned API families:
- `v1/*` — legacy endpoints served by `LegacyController`, mostly reading from MongoDB with direct queries
- `v2/*` — current endpoints: `IncidentController`, `WeatherController`, `RCMController`, `PlanesController`, `WarningsController`, `StatsController`

Write endpoints (`POST /v2/incidents/{id}/posit`, `POST /v2/incidents/{id}/kml`) are protected by an `API_WRITE_KEY` header check.

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

### Models (MongoDB)
All models extend `Jenssegers\Mongodb\Eloquent\Model`. Key models:
- `Incident` — collection `data`; central entity. Has scopes: `isActive()`, `isFire()`, `isFMA()`, `isOtherFire()`. Classification constants (`NATUREZA_CODE_*`) and `STATUS_ID`/`STATUS_COLORS` are defined here
- `IncidentHistory` / `IncidentStatusHistory` — historical records linked to `Incident` via `id` field
- `Location` — geocoding lookup table (level 1 = district, level 2 = concelho)
- `WeatherStation` / `WeatherData` / `WeatherDataDaily` — weather data
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

### Resources
API responses use Lumen resource classes in `app/Resources/`. `IncidentResource` shapes the main incident payload.

### Feature Flags (env)
Several features are toggled via `.env`:
- `SCHEDULER_ENABLE` — enable cron jobs
- `LEGACY_ENABLE` — enable v1 endpoints
- `TELEGRAM_ENABLE`, `TWITTER_ENABLE`, `FACEBOOK_ENABLE`, `DISCORD_ENABLE`, `NOTIFICATIONS_ENABLE` — social/notification channels
- `TROLL_MODE` — returns fake data for unauthorized API consumers (checks against whitelisted user-agents/referers)
- `PROXY_ENABLE` / `PROXY_URL` — route outbound requests through a proxy

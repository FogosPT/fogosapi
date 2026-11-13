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

# Delete exact duplicate weather warnings (idempotent; --dry-run to preview)
php artisan weather:collapse-warnings --dry-run
php artisan weather:collapse-warnings

# AEMET (Spain) imports, synchronous (need AEMET_API_KEY; first import after deploy, in this order)
php artisan aemet:stations       # station inventory
php artisan aemet:observations   # last 12 h of hourly observations
php artisan aemet:daily          # last 10 days of daily summaries
php artisan aemet:warnings       # CAP warnings snapshot (archive, zones, reconciliation)
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
- Enabling AEMET (Spain): set `AEMET_API_KEY` in the production `.env`, run the migrations (indexes), reload php-fpm, `queue:restart`, check `php artisan schedule:list` in the scheduler shows the four AEMET jobs, then run the first import by hand (as `-u www-data`): `aemet:stations`, `aemet:observations`, `aemet:daily`, `aemet:warnings`. No new dependencies.


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
- `HandleWeatherWarnings` — every 15 minutes; scrapes IPMA warnings from the homepage. The feed is a full snapshot, so each run reconciles the active rows of `weather_warnings` with it: one row per warning window, windows of the same `(district, type, level)` paired in start order and updated in place (IPMA shifts `startTime`/extends `endTime` on republish), new windows created (the STB Telegram observer fires on create), and active rows missing from the feed — replaced (yellow → orange → red) or cancelled — get `supersededAt` and are no longer served. Empty feeds and feeds older than the stored `reportDate` are ignored. The engine is `App\Support\WeatherWarningSync` (`WeatherWarningSync::ipma()`; `HandleWeatherWarnings::sync()` only delegates), parametrized by the warning/event models and shared with AEMET (see below). Reads filter `supersededAt` null and `endTime >= now`. `weather:collapse-warnings` only removes exact duplicates.
  - Pairing is per `(district, type)` by **strict overlap** (back-to-back windows don't pair): same level first (earliest window wins → updated in place), leftover rows overlapping a kept row of the same level are duplicates (superseded silently), then other levels by largest overlap (level change: old superseded, new created); a superseded row whose interval a kept row of another level now covers is also a level change (split merged back into one window), not a withdrawal. `terminado` endings are recorded on every run, even when the feed is rejected. `WeatherWarningSync::sync(?array $items, ?string $snapshotReportDate = null)` returns a `WeatherWarningDiff` (null when it bails out); IPMA never passes `$snapshotReportDate`.
  - `WeatherWarningEventRecorder` turns the diff into `weather_warning_events` (`App\Models\WeatherWarningEvent`): `emitido`, `agravado`, `desagravado`, `prolongado`, `encurtado`, `texto_alterado`, `retirado`, `terminado` (dropped ≤ 30 min before `endTime`, or expired in force — marks the row `endedAt`). Republishes that only move `startTime`/`reportDate` record nothing; a failed write is logged and never breaks the sync. `occurredAt` = snapshot's newest `reportDate` (`terminado`: `endTime`). Served by `GET /v2/warnings/ipma/history` (`from`/`to`/`area`/`type`/`kind`, default 7 days, max 31, `texto_alterado` only on request) — consumed by avisos.pt `/historico`.
  - `WeatherWarningCollapse` (read side of `/v2/warnings/ipma`) guarantees no two entries of the same `(district, type)` overlap: windows are cut by precedence (higher level — never under-report — then newest `reportDate`), pieces already over are dropped. `recordEnded` only looks back 24 h and the events migration backfills `endedAt` on rows that ended before the log existed.
- `DetectTemperatureWaves` — daily at 05:00 (after `UpdateWeatherDataDaily`); applies the WMO 6-day rule to detect heat waves (vs 1991-2020 normals) and cold waves (vs 1971-2000 normals) per station, persists `TemperatureWave` rows, and posts a Discord message on the first detection of each event

### Madeira (SRPC-RAM events)
`ProcessMadeiraEvents` polls the SRPC-RAM events feed every 2 minutes. The URL is **only** in `MADEIRA_EVENTS_URL` (kept out of the public code; empty → the job skips). The feed is a snapshot of the current adverse-weather event with aggregates only — per municipality (`oc`/`op`/`mt` + ANEPC nature codes), per nature and per hour — never individual occurrences, so nothing is written to `Incident`. Feed times are `Atlantic/Madeira` local, stored UTC.
- **History of everything, current version in the API.** `MadeiraFeedRevision` (`madeiraFeedRevisions`) archives every distinct version of the feed raw — including the IPMA warnings/forecasts we don't serve — deduplicated by `hash` = sha1 of the payload without `sincronizado` (which changes every poll); `eventId` null when the feed had no event. The endpoints only read `MadeiraEvent` (current version); anything past (e.g. when a road was closed and reopened) comes from the revisions.
- `MadeiraEvent` (`madeiraEvents`) — one document per event, `eventId = md5(title|start)` (a new start time is a new event), overwritten with the latest aggregates. Every other event still `active` is closed when the feed carries a different one; `estado != ATIVO` → `active=false`. A feed older than the newest stored `sourceUpdatedAt` is ignored.
- `MadeiraEventSnapshot` (`madeiraEventSnapshots`) — one KPI row per `(eventId, time)` (`time` = the feed's `atualizado`), for the evolution of active occurrences, operationals and ground assets.
- **Occurrences** — `ProcessMadeiraOccurrences` (every 5 min) reads the public statistics view of the SRPC-RAM ArcGIS (`MADEIRA_OCCURRENCES_URL` = layer URL, `…/FeatureServer/0`; empty → skips): one row per occurrence, all natures, since 2026-08 — `inicio`, `codigo`/`tipologiafonte`, `bombeiros`, `viaturas`, `codigocaop`/`freguesia`, `entidades`, `atualizadoem`. No geometry, status or occurrence number, so still no `Incident`s. **The objectid is positional, not an id** (the view is synced by position: a new or edited row moves the following ones to other objectids), so each content is a `MadeiraOccurrence` (`madeiraOccurrences`) with `key` = sha1 of every attribute but `objectid`/`atualizadoem` and `copies` = identical rows, plus our own stable `occurrenceId` (served as `id`). Each run diffs the table against the published contents; a content that disappears and one that appears are linked as the same occurrence edited when they are the only pair with the same start + nature (means/parish/corporations changed), or else the same start (nature reclassified), never with `copies > 1`: the new one inherits `occurrenceId` and `previousKey`, the old one gets `removedAt` + `replacedBy`. `MadeiraOccurrenceRevision` is the full change log per `occurrenceId`: `added`, `changed` (`changes` = field → [before, after]), `removed`, `restored`, `copies`; every one but `removed` archives the version as it was (`payload` raw content, `copies`, `sourceUpdatedAt`), and the history endpoint rebuilds versions from that, never from the (mutable) occurrence document. The whole table is read each run (paginated, 2000/page); a run that fails on any page, or returns fewer than half the contents we hold, writes nothing. Municipality = first 4 digits of the CAOP code (`MadeiraOccurrence::MUNICIPALITIES`); parish names are inconsistent in the source, so grouping is by code.
- **Approximate location** — the source only gives the parish, so occurrences (and the event's `parishes`) get `lat`/`lng` = the parish centroid with `location: "parish"` (null without a parish), computed when serving (nothing stored, no backfill). `App\Support\MadeiraParishes` reads `resources/data/madeira/parishes.json`: the 54 RAM parishes from the official DGT CAOP (WFS `caop_ram:ram_freguesias`, CC BY 4.0 — credited in `NOTICE`), centroid of the largest polygon on Madeira/Porto Santo (Sé includes the Selvagens, Santa Cruz the Desertas); regenerate with `php artisan madeira:parishes-import`. Unknown codes go through `ALIASES` (`310801` → `310805` Santa Cruz, seen in the source) and then a name match inside the municipality.
- **Health** — `App\Support\MadeiraFeedHealth` tracks the last usable response of each source (`events`, `occurrences`) in cache and posts once on the Discord errors channel (`DiscordTool::postError`) when one has failed for more than `MADEIRA_STALE_MINUTES` (30), and once when it recovers. For occurrences, a skipped run (table empty or less than half of what we hold) counts as a failure. A valid events feed without an event counts as working. Both jobs are `ShouldBeUnique` (`uniqueFor` 90 s / 180 s, only to bound a crashed worker); their `$timeout` must stay below the `redis` connection's `retry_after` (90 s) — ShouldBeUnique does not stop a redelivery of a reserved job — so the occurrences read has a 60 s budget (`timeout` 80).
- API (`MadeiraController`, 60 s cache): `v2/madeira/events[?limit=]`, `events/current` (`data: null` when none), `events/{id}`, `events/{id}/timeline`, `occurrences[?from&to&municipality&parish&nature&limit]` (last 24 h by default; removed rows excluded), `occurrences/{id}/history` (change log oldest first with the version after each change and the field diff, plus `current`, null once removed). The event detail adds `parishes`, `entities` and `topOccurrences` (10 by vehicles, then firefighters) computed from the occurrences inside the event period whose nature is one of the event's (counts weighted by `copies`).
- The event also keeps the feed's current `roadRestrictions` (`condicionamentos`, `closed` = `nivel: cortada`) and `communiques` (event communiqués + the latest official SRPC-RAM ones, `oficiais`, newest first, de-duplicated by link). On the event they are the current version only (a reopened road disappears); the past ones are in the revisions.
- The feed also carries IPMA warnings and ECMWF forecasts; they are archived in the revisions but not served.

### AEMET (Spain)
Spanish warnings and stations from AEMET OpenData (Agencia Estatal de Meteorología), served on their own endpoints **normalized to be identical to the IPMA ones** (same shape, keys, units, parameters, errors and cache), so a consumer of the IPMA endpoints (avisos.pt) can use them with the same code. Nothing is mixed into the IPMA endpoints. The only unavoidable difference is the ids: zone codes (`713603`, `713603C`) and station `idema` (`3469A`) are **strings** (case-sensitive), where IPMA has ints; `{id}` routes accept `[0-9A-Za-z]+`. Design: `docs/superpowers/specs/2026-11-12-aemet-design.md`.
- **Key** — `AEMET_API_KEY` (`config('services.aemet.key')`; empty → no AEMET job is scheduled and the jobs skip). The current key is a JWT that **expires on 2027-02-20**; renew it at https://opendata.aemet.es/centrodedescargas/altaUsuario (email + captcha) and update `.env` in production. A 401 (invalid/expired key) throws `AemetException` with `unauthorized` and its own message ("chave AEMET inválida ou expirada"), which is the reason carried by the health alert on Discord (posted once the source is stale, see Health). Never commit the key.
- **Two-step fetch** — `App\Tools\AemetTool`: the API path returns `{descripcion, estado, datos, metadatos}`; the payload is at the short-lived `datos` URL, fetched straight after. `estado` ≠ 200, HTTP ≠ 200 (401, 429 — no immediate retry), an empty body (what the API answers without a key) and invalid JSON are all failures (`AemetException`). JSON comes in ISO-8859-15 and is converted to UTF-8 before decoding; the warnings tar is returned raw.
- **Warnings** — `ProcessAemetWarnings` (every 15 min, `aemet:warnings`) reads `avisos_cap/ultimoelaborado/area/esp`: an uncompressed GNU tar of CAP 1.2 messages (`App\Support\Aemet\CapTar` via `PharData`, `CapMessage` parser; es-ES + en-GB `<info>`), a full snapshot that includes `verde` (no-warning) messages covering every zone and expired ones. Each run:
  - archives every message not stored yet in `AemetWarningMessage` (`aemetWarningMessages`, unique `identifier`, raw XML in `raw`) — **never rewritten**, verde and superseded ones included (full history);
  - upserts every area of every message in `AemetWarningZone` (`aemetWarningZones`, by `code`): `name`, `ccaa`, `province`, `coastal` (suffix `C`), `peninsula` (prefixes `64` Baleares, `65` Canarias, `78` Ceuta, `79` Melilla → false; `App\Support\Aemet\AemetZones`), `geometry` as GeoJSON MultiPolygon in lon/lat (CAP gives `lat,lon`);
  - turns the messages into **IPMA feed items** (`App\Support\Aemet\AemetWarningItems`): messages referenced by another one's `references`, `Cancel`s, `verde` and expired ones drop out; **one item per zone** (`idAreaAviso` = zone code, `awarenessLevelID` yellow/orange/red from amarillo/naranja/rojo, `startTime`/`endTime` = `onset`/`expires`, `text` = Spanish `description`, `reportDate` = `sent`); `awarenessTypeName` maps VI→Vento, PR→Precipitação, NE→Neve, TO→Trovoada, AT→Tempo Quente, BT→Tempo Frio, CO→Agitação Marítima, NI→Nevoeiro, and **unmapped phenomena keep the Spanish name** (e.g. "Polvo en suspensión", never null). Items of the same `(zone, type, level)` whose windows strictly overlap are **merged** into one (text/parameter ordered by the identifier's last segment), carrying all source `identifiers`. Extra fields: `areaName`, `phenomenon` (AEMET code), `parameter`, `probability`, `en` (headline/description/instruction);
  - reconciles `AemetWarning` (`aemetWarnings`, `district` = zone code) through the **same engine as IPMA**, `WeatherWarningSync::aemet()` (it builds its own `WeatherWarningEventRecorder` on `AemetWarning`/`AemetWarningEvent` — the container's recorder is the IPMA one), writing `aemetWarningEvents` with the same kinds. It passes `$snapshotReportDate` = the newest `sent` of **all** tar messages (verde included), so an all-verde tar is a valid all-clear that withdraws every active warning (`retirado`), and that date decides whether the snapshot is older than the stored rows. A tar with 0 messages, any unreadable file or a download error is a failed run: no reconciliation, only `terminado` endings are recorded. The STB Telegram observer does **not** apply to AEMET.
- **Stations** — `UpdateAemetStations` (daily 04:10, `aemet:stations`; inventory, ~925 stations, DMS → decimal, `peninsula` by province), `UpdateAemetObservations` (hourly at :25, `aemet:observations`; the feed covers the last 12 h, ~10 000 rows from ~850 stations, upsert by `(stationId, date)`; a station seen there but not in the inventory is created from `ubi`/`lat`/`lon`), `UpdateAemetDataDaily` (daily 10:15, `aemet:daily`; last 10 Spanish calendar days — published with ~3 days of delay and sometimes republished). Models `AemetStation` / `AemetObservation` / `AemetDataDaily`. **Normalized at write time to the IPMA names and units** (`App\Support\Aemet\AemetStationData`, original row kept in `raw`): hourly `temperatura`, `humidade`, `pressao` (station pressure), `precAcumulada`, `intensidadeVento` (m/s) + `intensidadeVentoKM` (×3.6), `idDireccVento` (0 calm, 1 N … 8 NW, 45° sectors), no `radiacao`; daily `DAILY_FIELDS` with decimal commas parsed, `prec` `Ip` → 0.0, `dir` tens of degrees ×10 (99/88 → null), `pressao` = mean of `presMax`/`presMin`, and wind in **km/h** (×3.6 — IPMA stores the daily wind in km/h, the hourly one in m/s). `AemetDataDaily.date` = UTC midnight of the (Spanish) calendar day.
- **Health** — `App\Support\AemetFeedHealth` (on the shared `App\Support\FeedHealth` base, like `MadeiraFeedHealth`), sources `warnings`, `observations`, `daily`: one Discord errors-channel post when a source has failed for more than `AEMET_STALE_MINUTES` (60; 48 h for `daily`) and one when it recovers. A rejected or older warnings snapshot counts as a failure. All four jobs are `ShouldBeUnique` with `$timeout` 80 (below the redis `retry_after` of 90 s).
- **API** (`AemetController`, sharing `Concerns\ServesWarningHistory` / `ServesStationSeries` with the IPMA controllers): `v2/warnings/aemet[?peninsula=1]` (like `v2/warnings/ipma` + `areaName`/`phenomenon`/`parameter`/`probability`, `WeatherWarningCollapse`, 60 s cache), `v2/warnings/aemet/history` (like `ipma/history`, on `aemetWarningEvents`), `v2/warnings/aemet/zones[?peninsula=1]` (GeoJSON FeatureCollection, 1 h cache); `v2/weather/aemet/stations[?peninsula=1]` (like `stations/ipma`; stations without coordinates are omitted), `v2/weather/aemet/observations` (like `v2/weather/observations`, `-99` for missing), `v2/weather/aemet/stations/{id}/observations?hours=` and `/daily?days=` (like the IPMA per-station endpoints, `null` for missing).
- Indexes: migration `2026_11_13_120000_create_aemet_indexes`. Licence: free reuse citing the source ("Fuente: AEMET", credited in `NOTICE` and the OpenAPI descriptions).

### Seismic (sismos.pt)
Earthquakes for the sismos.pt site (`v2/seismic/*`, `SeismicController`). IPMA's seismic feed only keeps ~30 days, so we archive it.
- `app/Tools/Seismic/*` — one `SeismicSource` per catalogue (`IpmaSeismicTool` areas 3+7, `EmscTool`, `UsgsTool`, `GeofonTool`, `IgnTool`), each parsing into a `SeismicEvent` DTO and dropping events outside `SeismicSource::BBOX`. IPMA times are UTC; `magnitud: "-99.0"` means not yet revised (stored as `magnitude=null`, `provisional=true`).
- `App\Support\EarthquakeMerger` — one `Earthquake` per real event: match by `sources.{src}.id`, else by origin within 10 s / 30 km (doubled for M5+). Preferred solution follows `SeismicSources::PRECEDENCE_PT` (IPMA first) inside PT regions, `PRECEDENCE_OTHER` outside; magnitude from the first source with a final value; place name from IPMA when present. Failover is implicit (no IPMA report → the next source leads).
- `App\Support\SeismicRegion` — `continente|acores|madeira|outro` by coarse polygons.
- `App\Support\SeismicPlaces` — nearest Portuguese places (gazetteer `resources/data/seismic/places-pt.json`: 309 municipality seats + 3229 parishes from Wikidata, CC0; regenerate with `php artisan seismic:places-import`). `nearest()` = nearest parish, nearest municipality seat, next parish of another municipality, with distance and bearing *from the place to the epicentre* (`N NE E SE S SO O NO`). The merger stores it as `nearestPlaces` when the location changes; `php artisan seismic:places [--missing] [--chunk=1000]` fills stored earthquakes. API: `nearest` on every earthquake, `nearestPlaces` on the detail.
- `App\Support\SeismicFeedHealth` — per-source freshness in cache, Discord on IPMA/EMSC outages, `primary()` for the site.
- `ProcessSeismicSource($source)` — scheduled per source when `SEISMIC_ENABLE=true` (IPMA/EMSC 2 min, USGS 10 min, GEOFON/IGN 15 min). `php artisan seismic:sync [source] [--days=]` runs it synchronously.
- History: `php artisan seismic:backfill {isc|emsc|usgs} --from=YYYY [--to=YYYY] [--minmag=] [--chunk=month|year] [--sleep=] [--retries=8]` — chunked, idempotent (through the merger); retries back off on 429/503 (Retry-After, else 1, 2, 4… min, capped at 15 min) and on network/5xx errors (10, 20, 40… s), never on other 4xx; default pause between requests is 10 s for the ISC (it rate-limits per IP) and 1 s otherwise; uses `Illuminate\Support\Sleep` so tests call `Sleep::fake()`; a chunk that returns a full page (`RangeSource::pageLimit()`) is split in two. ISC (`IscTool`, drops events without magnitude) is ~10× more complete than EMSC (it includes IPMA's small events) and is published up to a few months ago, so the intended split is ISC 1900→(now − 3 months), EMSC only for the gap. ISC is slow (~1 min per recent year).
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
- `MADEIRA_EVENTS_URL` — SRPC-RAM (Madeira) events feed; empty disables `ProcessMadeiraEvents`
- `MADEIRA_OCCURRENCES_URL` — SRPC-RAM ArcGIS statistics view (layer URL); empty disables `ProcessMadeiraOccurrences`
- `MADEIRA_STALE_MINUTES` — minutes without usable data before a Madeira source is announced on Discord (default 30)
- `AEMET_API_KEY` — AEMET OpenData key (JWT, expires 2027-02-20, renew at https://opendata.aemet.es/centrodedescargas/altaUsuario); empty disables every AEMET job
- `AEMET_STALE_MINUTES` — minutes without usable data before an AEMET source is announced on Discord (default 60; `daily` uses 48 h)
- `PHOTO_MODERATION_KEY` — header token guarding the photo moderation endpoints
- `PHOTO_UPLOAD_MAX_BYTES`, `PHOTO_RATE_PER_IP_PER_MINUTE`, `PHOTO_RATE_PER_INCIDENT_PER_IP_PER_HOUR`, `PHOTO_RATE_PER_INCIDENT_GLOBAL_PER_HOUR`, `PHOTO_PENDING_PER_INCIDENT_CAP` — photo upload limits and rate gates
- `MINIO_ENDPOINT`, `MINIO_PUBLIC_BASE_URL`, `MINIO_BUCKET`, `MINIO_REGION`, `MINIO_ROOT_USER`, `MINIO_ROOT_PASSWORD` — MinIO/S3 storage configuration

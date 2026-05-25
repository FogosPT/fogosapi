<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // data (Incident) — hottest collection, hit by ProcessANPCAllDataV2
        // every 2 min and CheckIsActive / CheckImportantFireIncident.
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('data', function (Blueprint $collection) {
            // whereFireId scope: legacy numeric `id` field.
            $collection->index(['id' => 1], 'id_idx', null, ['sparse' => true]);

            // CheckImportantFireIncident: active + isFire + sentCheckImportant + statusCode.
            // Also covers HourlySummary (active + isFire + statusCode).
            $collection->index(
                ['active' => 1, 'isFire' => 1, 'statusCode' => 1, 'sentCheckImportant' => 1],
                'active_fire_status_sent_idx'
            );

            // CheckIsActive: where('active', true) -> whereNotIn('id', [...]).
            $collection->index(['active' => 1], 'active_idx');

            // LegacyController historical range queries.
            $collection->index(['isFire' => 1, 'dateTime' => 1], 'fire_datetime_idx');

            // IncidentController filters.
            $collection->index(['concelho' => 1, 'active' => 1], 'concelho_active_idx');
            $collection->index(['sub_regiao' => 1, 'active' => 1], 'subregiao_active_idx');
            $collection->index(['isFMA' => 1, 'active' => 1], 'fma_active_idx');
            $collection->index(['naturezaCode' => 1], 'natureza_idx');

            // Standalone dateTime range (search endpoint without isFire prefix).
            $collection->index(['dateTime' => 1], 'datetime_idx');
        });

        // ---------------------------------------------------------------
        // locations — 2 lookups per incident inside ProcessANPCAllDataV2.
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('locations', function (Blueprint $collection) {
            $collection->index(['name' => 1, 'level' => 1], 'name_level_idx');
            $collection->index(['code' => 1, 'level' => 1], 'code_level_idx');
        });

        // ---------------------------------------------------------------
        // weatherData — hourly upserts by (stationId, date).
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('weatherData', function (Blueprint $collection) {
            $collection->index(['stationId' => 1, 'date' => 1], 'station_date_idx');
            $collection->index(['date' => 1], 'date_idx');
        });

        // ---------------------------------------------------------------
        // weatherDataDaily — daily aggregations and wave detection scans.
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('weatherDataDaily', function (Blueprint $collection) {
            $collection->index(['stationId' => 1, 'date' => 1], 'station_date_idx');
            $collection->index(['date' => 1], 'date_idx');
        });

        // ---------------------------------------------------------------
        // weatherNormals — read once per DetectTemperatureWaves run; small
        // but lookups happen by (stationId, period).
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('weatherNormals', function (Blueprint $collection) {
            $collection->index(['stationId' => 1, 'period' => 1], 'station_period_idx');
        });

        // ---------------------------------------------------------------
        // temperatureWaves — upsert (stationId, type, start_date) +
        // public endpoint filter (type, ongoing).
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('temperatureWaves', function (Blueprint $collection) {
            $collection->index(
                ['stationId' => 1, 'type' => 1, 'start_date' => 1],
                'station_type_start_idx'
            );
            $collection->index(['type' => 1, 'ongoing' => 1], 'type_ongoing_idx');
            $collection->index(
                ['stationId' => 1, 'type' => 1, 'ongoing' => 1],
                'station_type_ongoing_idx'
            );
        });

        // ---------------------------------------------------------------
        // weatherStations — joined by stationId from TemperatureWave,
        // WeatherNormal, IncidentResource.
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('weatherStations', function (Blueprint $collection) {
            $collection->index(['stationId' => 1], 'stationid_idx');
        });

        // ---------------------------------------------------------------
        // history (IncidentHistory) — scope filters by incidentId or id,
        // ordered by created.
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('history', function (Blueprint $collection) {
            $collection->index(['incidentId' => 1, 'created' => 1], 'incident_created_idx');
            $collection->index(['id' => 1, 'created' => 1], 'id_created_idx');
        });

        // ---------------------------------------------------------------
        // statusHistory (IncidentStatusHistory).
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('statusHistory', function (Blueprint $collection) {
            $collection->index(['incidentId' => 1, 'created' => 1], 'incident_created_idx');
            $collection->index(['id' => 1, 'created' => 1], 'id_created_idx');
        });

        // ---------------------------------------------------------------
        // incident_photos — moderation queue + per-incident listings.
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('incident_photos', function (Blueprint $collection) {
            $collection->index(['fire_id' => 1, 'status' => 1], 'fireid_status_idx');
            $collection->index(['status' => 1, 'created_at' => 1], 'status_created_idx');
        });

        // ---------------------------------------------------------------
        // rcm — danger lookup by concelho, latest first.
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('rcm', function (Blueprint $collection) {
            $collection->index(['concelho' => 1, 'created' => 1], 'concelho_created_idx');
        });

        // ---------------------------------------------------------------
        // rcmJS — public endpoints filter by `when` (hoje/amanha/depois).
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('rcmJS', function (Blueprint $collection) {
            $collection->index(['when' => 1, 'created' => 1], 'when_created_idx');
        });

        // ---------------------------------------------------------------
        // hotspots — filtered by incident_id.
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('hotspots', function (Blueprint $collection) {
            $collection->index(['incident_id' => 1], 'incident_id_idx');
        });

        // ---------------------------------------------------------------
        // warningMadeira — scope by id, ordered by created.
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('warningMadeira', function (Blueprint $collection) {
            $collection->index(['id' => 1, 'created' => 1], 'id_created_idx');
            $collection->index(['created' => 1], 'created_idx');
        });

        // ---------------------------------------------------------------
        // Other order-by-created collections (small, but cheap to index).
        // ---------------------------------------------------------------
        Schema::connection('mongodb')->table('warning', function (Blueprint $collection) {
            $collection->index(['created' => 1], 'created_idx');
        });

        Schema::connection('mongodb')->table('warningSite', function (Blueprint $collection) {
            $collection->index(['created' => 1], 'created_idx');
        });

        Schema::connection('mongodb')->table('historyTotal', function (Blueprint $collection) {
            $collection->index(['created' => 1], 'created_idx');
        });

        Schema::connection('mongodb')->table('pplanes', function (Blueprint $collection) {
            $collection->index(['created' => 1], 'created_idx');
        });
    }

    public function down(): void
    {
        $drops = [
            'data' => [
                'id_idx',
                'active_fire_status_sent_idx',
                'active_idx',
                'fire_datetime_idx',
                'concelho_active_idx',
                'subregiao_active_idx',
                'fma_active_idx',
                'natureza_idx',
                'datetime_idx',
            ],
            'locations'        => ['name_level_idx', 'code_level_idx'],
            'weatherData'      => ['station_date_idx', 'date_idx'],
            'weatherDataDaily' => ['station_date_idx', 'date_idx'],
            'weatherNormals'   => ['station_period_idx'],
            'temperatureWaves' => [
                'station_type_start_idx',
                'type_ongoing_idx',
                'station_type_ongoing_idx',
            ],
            'weatherStations'  => ['stationid_idx'],
            'history'          => ['incident_created_idx', 'id_created_idx'],
            'statusHistory'    => ['incident_created_idx', 'id_created_idx'],
            'incident_photos'  => ['fireid_status_idx', 'status_created_idx'],
            'rcm'              => ['concelho_created_idx'],
            'rcmJS'            => ['when_created_idx'],
            'hotspots'         => ['incident_id_idx'],
            'warningMadeira'   => ['id_created_idx', 'created_idx'],
            'warning'          => ['created_idx'],
            'warningSite'      => ['created_idx'],
            'historyTotal'     => ['created_idx'],
            'pplanes'          => ['created_idx'],
        ];

        foreach ($drops as $table => $indexes) {
            Schema::connection('mongodb')->table($table, function (Blueprint $collection) use ($indexes) {
                foreach ($indexes as $name) {
                    $collection->dropIndex($name);
                }
            });
        }
    }
};

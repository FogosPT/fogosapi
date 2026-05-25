<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\CommandException;

return new class extends Migration
{
    /**
     * All indexes to create, grouped by collection.
     * Each entry: [keys, name].
     */
    private function indexes(): array
    {
        return [
            // data (Incident) — hottest collection, hit by ProcessANPCAllDataV2
            // every 2 min and CheckIsActive / CheckImportantFireIncident.
            'data' => [
                [['id' => 1], 'id_idx', ['sparse' => true]],
                [['active' => 1, 'isFire' => 1, 'statusCode' => 1, 'sentCheckImportant' => 1], 'active_fire_status_sent_idx'],
                [['active' => 1], 'active_idx'],
                [['isFire' => 1, 'dateTime' => 1], 'fire_datetime_idx'],
                [['concelho' => 1, 'active' => 1], 'concelho_active_idx'],
                [['sub_regiao' => 1, 'active' => 1], 'subregiao_active_idx'],
                [['isFMA' => 1, 'active' => 1], 'fma_active_idx'],
                [['naturezaCode' => 1], 'natureza_idx'],
                [['dateTime' => 1], 'datetime_idx'],
            ],

            // locations — 2 lookups per incident inside ProcessANPCAllDataV2.
            'locations' => [
                [['name' => 1, 'level' => 1], 'name_level_idx'],
                [['code' => 1, 'level' => 1], 'code_level_idx'],
            ],

            // weatherData — hourly upserts by (stationId, date).
            'weatherData' => [
                [['stationId' => 1, 'date' => 1], 'station_date_idx'],
                [['date' => 1], 'date_idx'],
            ],

            // weatherDataDaily — daily aggregations and wave detection scans.
            'weatherDataDaily' => [
                [['stationId' => 1, 'date' => 1], 'station_date_idx'],
                [['date' => 1], 'date_idx'],
            ],

            // weatherNormals — read once per DetectTemperatureWaves run.
            'weatherNormals' => [
                [['stationId' => 1, 'period' => 1], 'station_period_idx'],
            ],

            // temperatureWaves — upsert (stationId, type, start_date) +
            // public endpoint filter (type, ongoing).
            'temperatureWaves' => [
                [['stationId' => 1, 'type' => 1, 'start_date' => 1], 'station_type_start_idx'],
                [['type' => 1, 'ongoing' => 1], 'type_ongoing_idx'],
                [['stationId' => 1, 'type' => 1, 'ongoing' => 1], 'station_type_ongoing_idx'],
            ],

            // weatherStations — joined by stationId from several places.
            'weatherStations' => [
                [['stationId' => 1], 'stationid_idx'],
            ],

            // history (IncidentHistory) — scope filters by incidentId or id,
            // ordered by created.
            'history' => [
                [['incidentId' => 1, 'created' => 1], 'incident_created_idx'],
                [['id' => 1, 'created' => 1], 'id_created_idx'],
            ],

            // statusHistory (IncidentStatusHistory).
            'statusHistory' => [
                [['incidentId' => 1, 'created' => 1], 'incident_created_idx'],
                [['id' => 1, 'created' => 1], 'id_created_idx'],
            ],

            // incident_photos — moderation queue + per-incident listings.
            'incident_photos' => [
                [['fire_id' => 1, 'status' => 1], 'fireid_status_idx'],
                [['status' => 1, 'created_at' => 1], 'status_created_idx'],
            ],

            // rcm — danger lookup by concelho, latest first.
            'rcm' => [
                [['concelho' => 1, 'created' => 1], 'concelho_created_idx'],
            ],

            // rcmJS — public endpoints filter by `when` (hoje/amanha/depois).
            'rcmJS' => [
                [['when' => 1, 'created' => 1], 'when_created_idx'],
            ],

            // hotspots — filtered by incident_id.
            'hotspots' => [
                [['incident_id' => 1], 'incident_id_idx'],
            ],

            // warningMadeira — scope by id, ordered by created.
            'warningMadeira' => [
                [['id' => 1, 'created' => 1], 'id_created_idx'],
                [['created' => 1], 'created_idx'],
            ],

            // Other order-by-created collections.
            'warning' => [
                [['created' => 1], 'created_idx'],
            ],
            'warningSite' => [
                [['created' => 1], 'created_idx'],
            ],
            'historyTotal' => [
                [['created' => 1], 'created_idx'],
            ],
            'pplanes' => [
                [['created' => 1], 'created_idx'],
            ],
        ];
    }

    public function up(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        foreach ($this->indexes() as $table => $specs) {
            $collection = $db->selectCollection($table);

            foreach ($specs as $spec) {
                [$keys, $name] = [$spec[0], $spec[1]];
                $options = ($spec[2] ?? []) + ['name' => $name];

                try {
                    $collection->createIndex($keys, $options);
                } catch (CommandException $e) {
                    // 85 = IndexOptionsConflict, 86 = IndexKeySpecsConflict.
                    // Both mean an equivalent index already exists under a
                    // different name or with different non-key options. The
                    // existing index already serves the query — keep going.
                    if (in_array($e->getCode(), [85, 86], true)) {
                        continue;
                    }
                    throw $e;
                }
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        foreach ($this->indexes() as $table => $specs) {
            $collection = $db->selectCollection($table);

            foreach ($specs as $spec) {
                $name = $spec[1];

                try {
                    $collection->dropIndex($name);
                } catch (CommandException $e) {
                    // 27 = IndexNotFound — nothing to drop, ignore.
                    if ($e->getCode() === 27) {
                        continue;
                    }
                    throw $e;
                }
            }
        }
    }
};

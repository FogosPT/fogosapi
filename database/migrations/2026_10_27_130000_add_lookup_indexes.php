<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\CommandException;

return new class extends Migration
{
    /**
     * "Latest row for X" lookups that had no index, so each one scanned and
     * sorted the whole (growing) collection.
     */
    private function indexes(): array
    {
        return [
            // PlanesController index/active/track (one query per aircraft) and
            // ProcessFR24Planes first-sighting check; written every minute.
            'flight_positions' => [
                [['icao' => 1, 'created' => -1], 'icao_created_idx'],
            ],
            // /v2/planes/{icao}
            'pplanes' => [
                [['icao' => 1, 'created' => -1], 'icao_created_idx'],
            ],
            // FirePerimetersController per-incident endpoints.
            'fire_perimeters_history' => [
                [['incident_id' => 1, 'fetched_at' => -1], 'incident_fetched_idx'],
            ],
            'fire_simulations_history' => [
                [['incident_id' => 1, 'fetched_at' => -1], 'incident_fetched_idx'],
            ],
            // /v2/incidents/{id}/kml/history
            'incident_kml_history' => [
                [['incident_id' => 1, 'created' => -1], 'incident_created_idx'],
            ],
            // Active warnings (IncidentResource, /v2/warnings/ipma).
            'weather_warnings' => [
                [['endTime' => 1], 'endtime_idx'],
            ],
        ];
    }

    public function up(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        foreach ($this->indexes() as $collection => $specs) {
            foreach ($specs as [$keys, $name]) {
                try {
                    $db->selectCollection($collection)->createIndex($keys, ['name' => $name]);
                } catch (CommandException $e) {
                    // 85/86: an equivalent index already exists under another name.
                    if (!in_array($e->getCode(), [85, 86], true)) {
                        throw $e;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        foreach ($this->indexes() as $collection => $specs) {
            foreach ($specs as [, $name]) {
                try {
                    $db->selectCollection($collection)->dropIndex($name);
                } catch (CommandException $e) {
                    if ($e->getCode() !== 27) {
                        throw $e;
                    }
                }
            }
        }
    }
};

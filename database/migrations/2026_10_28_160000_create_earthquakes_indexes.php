<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Exception\CommandException;

return new class () extends Migration {
    /**
     * Indexes for the `earthquakes` collection (sismos.pt): time-ordered
     * listings per region, the merger's per-source id lookups and $near
     * queries on the epicentre.
     */
    private const INDEXES = [
        'location_2dsphere_idx' => ['location' => '2dsphere'],
        'time_idx' => ['time' => -1],
        'region_time_idx' => ['region' => 1, 'time' => -1],
        'region_magnitude_idx' => ['region' => 1, 'magnitude' => -1],
        'ipma_id_idx' => ['sources.ipma.id' => 1],
        'emsc_id_idx' => ['sources.emsc.id' => 1],
        'usgs_id_idx' => ['sources.usgs.id' => 1],
        'geofon_id_idx' => ['sources.geofon.id' => 1],
        'ign_id_idx' => ['sources.ign.id' => 1],
    ];

    public function up(): void
    {
        $collection = DB::connection('mongodb')->getMongoDB()->selectCollection('earthquakes');

        foreach (self::INDEXES as $name => $keys) {
            try {
                $collection->createIndex($keys, ['name' => $name]);
            } catch (CommandException $e) {
                if (!in_array($e->getCode(), [85, 86], true)) {
                    Log::warning("earthquakes index {$name} not created: ".$e->getMessage());
                    echo "WARNING: earthquakes index {$name} not created: ".$e->getMessage().PHP_EOL;
                }
            }
        }
    }

    public function down(): void
    {
        $collection = DB::connection('mongodb')->getMongoDB()->selectCollection('earthquakes');

        foreach (array_keys(self::INDEXES) as $name) {
            try {
                $collection->dropIndex($name);
            } catch (CommandException $e) {
                if ($e->getCode() !== 27) {
                    throw $e;
                }
            }
        }
    }
};

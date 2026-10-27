<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Exception\CommandException;

return new class extends Migration
{
    /**
     * AssignNearestWeatherStation uses $near on weatherStations.geoJSON, which
     * fails without a 2dsphere index ("unable to find index for $geoNear").
     */
    public function up(): void
    {
        $collection = DB::connection('mongodb')->getMongoDB()->selectCollection('weatherStations');

        try {
            $collection->createIndex(['geoJSON' => '2dsphere'], ['name' => 'geojson_2dsphere_idx']);
        } catch (CommandException $e) {
            // 85/86: an equivalent index already exists. Anything else (e.g. a
            // station with malformed geoJSON) must not block the deploy.
            if (!in_array($e->getCode(), [85, 86], true)) {
                Log::warning('weatherStations 2dsphere index not created: '.$e->getMessage());
                echo 'WARNING: weatherStations 2dsphere index not created: '.$e->getMessage().PHP_EOL;
            }
        }
    }

    public function down(): void
    {
        try {
            DB::connection('mongodb')->getMongoDB()->selectCollection('weatherStations')->dropIndex('geojson_2dsphere_idx');
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }
    }
};

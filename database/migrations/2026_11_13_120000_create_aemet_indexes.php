<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Exception\CommandException;

/**
 * Every AEMET index (warnings and stations), mirroring the IPMA ones.
 * 85/86 (an equivalent index already exists) are ignored; anything else is
 * logged and must not block the deploy.
 */
return new class () extends Migration {
    private const INDEXES = [
        // Reconciliation and /v2/warnings/aemet (active rows, endings).
        'aemetWarnings' => [
            'endtime_idx' => [['endTime' => 1], []],
            'ended_idx'   => [['endedAt' => 1, 'supersededAt' => 1, 'endTime' => 1], []],
            'district_idx' => [['district' => 1, 'type' => 1], []],
        ],
        // History endpoint.
        'aemetWarningEvents' => [
            'occurred_idx'          => [['occurredAt' => -1], []],
            'district_occurred_idx' => [['district' => 1, 'occurredAt' => -1], []],
        ],
        // CAP archive: one document per identifier.
        'aemetWarningMessages' => [
            'identifier_unique' => [['identifier' => 1], ['unique' => true]],
            'zones_expires_idx' => [['zones' => 1, 'expires' => 1], []],
        ],
        'aemetWarningZones' => [
            'code_unique' => [['code' => 1], ['unique' => true]],
        ],
        // Stations (Task 7): coordinates are [lng, lat] like the IPMA ones.
        'aemetStations' => [
            'stationid_unique'         => [['stationId' => 1], ['unique' => true]],
            'coordinates_2dsphere_idx' => [['coordinates' => '2dsphere'], []],
        ],
        // Upserts by (stationId, date) and the time-window reads.
        'aemetObservations' => [
            'station_date_unique' => [['stationId' => 1, 'date' => 1], ['unique' => true]],
            'date_idx'            => [['date' => 1], []],
        ],
        'aemetDataDaily' => [
            'station_date_unique' => [['stationId' => 1, 'date' => 1], ['unique' => true]],
            'date_idx'            => [['date' => 1], []],
        ],
    ];

    public function up(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        foreach (self::INDEXES as $collection => $indexes) {
            foreach ($indexes as $name => [$keys, $options]) {
                try {
                    $db->selectCollection($collection)->createIndex($keys, ['name' => $name] + $options);
                } catch (CommandException $e) {
                    if (!in_array($e->getCode(), [85, 86], true)) {
                        Log::warning("{$collection} index {$name} not created: ".$e->getMessage());
                        echo "WARNING: {$collection} index {$name} not created: ".$e->getMessage().PHP_EOL;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        foreach (self::INDEXES as $collection => $indexes) {
            foreach (array_keys($indexes) as $name) {
                try {
                    $db->selectCollection($collection)->dropIndex($name);
                } catch (CommandException $e) {
                    // 26: no such collection, 27: no such index.
                    if (!in_array($e->getCode(), [26, 27], true)) {
                        throw $e;
                    }
                }
            }
        }
    }
};

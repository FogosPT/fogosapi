<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Exception\CommandException;

return new class () extends Migration {
    /**
     * Indexes for the SRPC-RAM (Madeira) events: lookup by eventId, listings
     * newest first, one KPI snapshot per (event, feed update) and one raw
     * feed revision per distinct payload; occurrences by objectid and time.
     */
    private const INDEXES = [
        'madeiraEvents' => [
            'eventId_unique' => [['eventId' => 1], ['unique' => true]],
            'start_idx' => [['start' => -1], []],
            'active_idx' => [['active' => 1, 'sourceUpdatedAt' => -1], []],
        ],
        'madeiraEventSnapshots' => [
            'eventId_time_unique' => [['eventId' => 1, 'time' => 1], ['unique' => true]],
        ],
        'madeiraOccurrences' => [
            'sourceId_unique' => [['sourceId' => 1], ['unique' => true]],
            'start_idx' => [['removedAt' => 1, 'start' => -1], []],
            'municipality_start_idx' => [['municipalityCode' => 1, 'start' => -1], []],
        ],
        'madeiraOccurrenceRevisions' => [
            'sourceId_idx' => [['sourceId' => 1, 'created' => 1], []],
        ],
        'madeiraFeedRevisions' => [
            'hash_unique' => [['hash' => 1], ['unique' => true]],
            'eventId_sourceUpdatedAt_idx' => [['eventId' => 1, 'sourceUpdatedAt' => 1], []],
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
                    if ($e->getCode() !== 27) {
                        throw $e;
                    }
                }
            }
        }
    }
};

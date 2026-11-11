<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Exception\CommandException;

return new class () extends Migration {
    private const INDEXES = [
        'occurred_idx'          => ['occurredAt' => -1],
        'district_occurred_idx' => ['district' => 1, 'occurredAt' => -1],
    ];

    public function up(): void
    {
        $collection = DB::connection('mongodb')->getMongoDB()->selectCollection('weather_warning_events');

        foreach (self::INDEXES as $name => $keys) {
            try {
                $collection->createIndex($keys, ['name' => $name]);
            } catch (CommandException $e) {
                if (!in_array($e->getCode(), [85, 86], true)) {
                    Log::warning("weather_warning_events index {$name} not created: ".$e->getMessage());
                    echo "WARNING: weather_warning_events index {$name} not created: ".$e->getMessage().PHP_EOL;
                }
            }
        }

        try {
            DB::connection('mongodb')->getMongoDB()->selectCollection('weather_warnings')
                ->createIndex(['endedAt' => 1, 'supersededAt' => 1, 'endTime' => 1], ['name' => 'ended_idx']);
        } catch (CommandException $e) {
            if (!in_array($e->getCode(), [85, 86], true)) {
                Log::warning('weather_warnings ended_idx not created: '.$e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        foreach (array_keys(self::INDEXES) as $name) {
            try {
                $db->selectCollection('weather_warning_events')->dropIndex($name);
            } catch (CommandException $e) {
                if ($e->getCode() !== 27) {
                    throw $e;
                }
            }
        }

        try {
            $db->selectCollection('weather_warnings')->dropIndex('ended_idx');
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }
    }
};

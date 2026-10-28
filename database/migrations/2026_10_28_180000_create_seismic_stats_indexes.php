<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Exception\CommandException;

return new class () extends Migration {
    public function up(): void
    {
        try {
            DB::connection('mongodb')->getMongoDB()->selectCollection('seismic_stats')
                ->createIndex(['region' => 1, 'window' => 1], ['name' => 'region_window_idx', 'unique' => true]);
        } catch (CommandException $e) {
            if (!in_array($e->getCode(), [85, 86], true)) {
                Log::warning('seismic_stats index not created: '.$e->getMessage());
                echo 'WARNING: seismic_stats index not created: '.$e->getMessage().PHP_EOL;
            }
        }
    }

    public function down(): void
    {
        try {
            DB::connection('mongodb')->getMongoDB()->selectCollection('seismic_stats')->dropIndex('region_window_idx');
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\CommandException;

return new class () extends Migration {
    /**
     * /v2/incidents/search sorts by (dateTime desc, _id desc). These indexes
     * give that order without a blocking in-memory sort (walked backwards):
     * the first one for the default fire-only search, the second for all=1.
     */
    private function indexes(): array
    {
        return [
            [['isFire' => 1, 'dateTime' => 1, '_id' => 1], 'fire_datetime_id_idx'],
            [['dateTime' => 1, '_id' => 1], 'datetime_id_idx'],
        ];
    }

    public function up(): void
    {
        $collection = DB::connection('mongodb')->getMongoDB()->selectCollection('data');

        foreach ($this->indexes() as [$keys, $name]) {
            try {
                $collection->createIndex($keys, ['name' => $name]);
            } catch (CommandException $e) {
                // 85 = IndexOptionsConflict, 86 = IndexKeySpecsConflict: an
                // equivalent index already exists under another name.
                if (in_array($e->getCode(), [85, 86], true)) {
                    continue;
                }

                throw $e;
            }
        }
    }

    public function down(): void
    {
        $collection = DB::connection('mongodb')->getMongoDB()->selectCollection('data');

        foreach ($this->indexes() as [, $name]) {
            try {
                $collection->dropIndex($name);
            } catch (CommandException $e) {
                // 27 = IndexNotFound.
                if ($e->getCode() === 27) {
                    continue;
                }

                throw $e;
            }
        }
    }
};

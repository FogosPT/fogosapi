<?php

namespace App\Jobs;

use App\Models\AemetDataDaily;
use App\Support\Aemet\AemetStationData;
use App\Support\AemetFeedHealth;
use App\Tools\AemetException;
use App\Tools\AemetTool;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\UTCDateTime;

/**
 * Imports the AEMET (Spain) daily climatological summaries of the last 10 days
 * (published with ~3 days of delay and sometimes republished) into
 * `aemetDataDaily`, upserted by `(stationId, date)` — `date` = the civil day of
 * `fecha` as UTC midnight — in the IPMA `DAILY_FIELDS` (AemetStationData::daily)
 * plus `raw`. The days requested are Spanish (Europe/Madrid) calendar days.
 * A failed download or an empty feed counts as a failure of the `daily` source
 * in AemetFeedHealth (48 h window). Does nothing without `AEMET_API_KEY`.
 */
class UpdateAemetDataDaily extends Job implements ShouldBeUnique
{
    public const DAYS = 10;

    private const CHUNK = 1000;

    /** Below the redis `retry_after` (90 s). */
    public $timeout = 80;

    /** Lock released when the job ends; this only bounds a crashed worker. */
    public $uniqueFor = 3600;

    /** The health message posted by this run, if any (tests). */
    public ?string $healthMessage = null;

    /** Why this run failed (download, empty feed, write); null on success. */
    public ?string $failureReason = null;

    /** Summaries upserted by this run. */
    public int $imported = 0;

    /** Only set synchronously (tests); queued jobs build their own. */
    private ?AemetTool $tool = null;

    public function __construct() {}

    public function withTool(AemetTool $tool): self
    {
        $this->tool = $tool;

        return $this;
    }

    public function handle(): void
    {
        if ((string) config('services.aemet.key') === '') {
            Log::debug('[UpdateAemetDataDaily] AEMET_API_KEY not set, skipping.');

            return;
        }

        $today = Carbon::now('Europe/Madrid')->startOfDay();

        try {
            $rows = ($this->tool ?? new AemetTool())->daily($today->copy()->subDays(self::DAYS), $today);
        } catch (AemetException $e) {
            $this->reject($e->getMessage());

            return;
        }

        $operations = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['indicativo'] ?? ''));
            $fecha = (string) ($row['fecha'] ?? '');
            if ($id === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                continue;
            }

            $operations[] = ['updateOne' => [
                ['stationId' => $id, 'date' => new UTCDateTime(Carbon::createFromFormat('!Y-m-d', $fecha, 'UTC'))],
                ['$set' => AemetStationData::daily($row) + ['raw' => $row]],
                ['upsert' => true],
            ]];
        }

        if ($operations === []) {
            $this->reject('feed sem resumos diários');

            return;
        }

        try {
            foreach (array_chunk($operations, self::CHUNK) as $chunk) {
                AemetDataDaily::raw(fn ($collection) => $collection->bulkWrite($chunk, ['ordered' => false]));
            }
        } catch (\Throwable $e) {
            report($e);
            $this->reject('resumos diários não gravados: '.$e->getMessage());

            return;
        }

        $this->imported = count($operations);
        $this->healthMessage = AemetFeedHealth::recordSuccess('daily');
    }

    private function reject(string $reason): void
    {
        Log::warning('[UpdateAemetDataDaily] '.$reason);
        $this->failureReason = $reason;
        $this->healthMessage = AemetFeedHealth::recordFailure('daily', $reason);
    }
}

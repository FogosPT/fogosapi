<?php

namespace App\Jobs;

use App\Models\AemetStation;
use App\Support\Aemet\AemetStationData;
use App\Support\AemetFeedHealth;
use App\Tools\AemetException;
use App\Tools\AemetTool;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\UTCDateTime;

/**
 * Upserts the AEMET (Spain) station inventory into `aemetStations` by `stationId`
 * (DMS coordinates → decimal [lng, lat], `peninsula` by province). Stations missing
 * from the inventory are kept. A 401 is announced at once
 * (AemetFeedHealth::recordUnauthorized). Does nothing without `AEMET_API_KEY`.
 */
class UpdateAemetStations extends Job implements ShouldBeUnique
{
    /** Below the redis `retry_after` (90 s). */
    public $timeout = 80;

    /** Lock released when the job ends; this only bounds a crashed worker. */
    public $uniqueFor = 3600;

    /** Why this run failed (download, empty or unusable inventory); null on success. */
    public ?string $failureReason = null;

    /** Stations upserted by this run. */
    public int $imported = 0;

    /** Only set synchronously (tests); queued jobs build their own. */
    private ?AemetTool $tool = null;

    /** The unauthorized-key message posted by this run, if any (tests). */
    public ?string $unauthorizedMessage = null;

    /** Own worker (`fogos.worker-aemet`): never delays the ANEPC ingestion on `default`. */
    public function __construct()
    {
        $this->onQueue('aemet');
    }

    public function withTool(AemetTool $tool): self
    {
        $this->tool = $tool;

        return $this;
    }

    public function handle(): void
    {
        if ((string) config('services.aemet.key') === '') {
            Log::debug('[UpdateAemetStations] AEMET_API_KEY not set, skipping.');

            return;
        }

        try {
            $rows = ($this->tool ?? new AemetTool())->inventory();
        } catch (AemetException $e) {
            if ($e->unauthorized) {
                $this->unauthorizedMessage = AemetFeedHealth::recordUnauthorized('stations', $e->getMessage());
            }
            $this->reject($e->getMessage());

            return;
        }

        $now = new UTCDateTime(Carbon::now());
        $operations = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['indicativo'] ?? ''));
            $lat = AemetStationData::dms((string) ($row['latitud'] ?? ''));
            $lng = AemetStationData::dms((string) ($row['longitud'] ?? ''));
            if ($id === '' || $lat === null || $lng === null) {
                continue;
            }

            $province = (string) ($row['provincia'] ?? '');
            $operations[] = ['updateOne' => [
                ['stationId' => $id],
                ['$set' => [
                    'stationId'   => $id,
                    'location'    => $row['nombre'] ?? null,
                    'place'       => $province !== '' ? $province : null,
                    'coordinates' => [$lng, $lat],
                    'altitude'    => AemetStationData::number($row['altitud'] ?? null),
                    'peninsula'   => AemetStationData::peninsula($province),
                    'lastSeenAt'  => $now,
                ]],
                ['upsert' => true],
            ]];
        }

        if ($operations === []) {
            $this->reject('inventário vazio');

            return;
        }

        try {
            AemetStation::raw(fn ($collection) => $collection->bulkWrite($operations, ['ordered' => false]));
        } catch (\Throwable $e) {
            report($e);
            $this->reject('estações não gravadas: '.$e->getMessage());

            return;
        }

        $this->imported = count($operations);
        // Daily, so only the unauthorized alert (and its reset) is tracked, not staleness.
        AemetFeedHealth::recordSuccess('stations');
    }

    private function reject(string $reason): void
    {
        Log::warning('[UpdateAemetStations] '.$reason);
        $this->failureReason = $reason;
    }
}

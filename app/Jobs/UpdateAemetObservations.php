<?php

namespace App\Jobs;

use App\Models\AemetObservation;
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
 * Imports the last ~12 h of AEMET (Spain) conventional observations into
 * `aemetObservations`, upserted by `(stationId, date)` (`date` = `fint`, UTC) in
 * the IPMA fields and units (AemetStationData::hourly) plus `raw`. Hourly: the
 * feed's 12 h overlap means an isolated failure leaves no gap. A station not in
 * `aemetStations` is created from the row (`ubi`/`lat`/`lon`/`alt`); every station
 * seen gets `lastSeenAt`. A failed download or an empty feed counts as a failure
 * of the `observations` source in AemetFeedHealth.
 * Does nothing without `AEMET_API_KEY`.
 */
class UpdateAemetObservations extends Job implements ShouldBeUnique
{
    private const CHUNK = 1000;

    /** Below the redis `retry_after` (90 s). */
    public $timeout = 80;

    /** Lock released when the job ends; this only bounds a crashed worker. */
    public $uniqueFor = 1800;

    /** The health message posted by this run, if any (tests). */
    public ?string $healthMessage = null;

    /** Why this run failed (download, empty feed, write); null on success. */
    public ?string $failureReason = null;

    /** Observations upserted by this run. */
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
            Log::debug('[UpdateAemetObservations] AEMET_API_KEY not set, skipping.');

            return;
        }

        try {
            $rows = ($this->tool ?? new AemetTool())->observations();
        } catch (AemetException $e) {
            $this->reject($e->getMessage());

            return;
        }

        $operations = [];
        $stations = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['idema'] ?? ''));
            if ($id === '' || empty($row['fint'])) {
                continue;
            }

            try {
                $date = Carbon::parse((string) $row['fint'])->utc();
            } catch (\Throwable) {
                continue;
            }

            $operations[] = ['updateOne' => [
                ['stationId' => $id, 'date' => new UTCDateTime($date)],
                ['$set' => AemetStationData::hourly($row) + ['raw' => $row]],
                ['upsert' => true],
            ]];
            $stations[$id] = $row; // the latest row of each station
        }

        if ($operations === []) {
            $this->reject('feed sem observações');

            return;
        }

        try {
            foreach (array_chunk($operations, self::CHUNK) as $chunk) {
                AemetObservation::raw(fn ($collection) => $collection->bulkWrite($chunk, ['ordered' => false]));
            }
            $this->stations($stations);
        } catch (\Throwable $e) {
            report($e);
            $this->reject('observações não gravadas: '.$e->getMessage());

            return;
        }

        $this->imported = count($operations);
        $this->healthMessage = AemetFeedHealth::recordSuccess('observations');
    }

    /**
     * Marks every station seen; one not in the inventory is created from its row
     * (the inventory fields are never overwritten here).
     *
     * @param array<string, array> $stations stationId → latest row
     */
    private function stations(array $stations): void
    {
        $now = new UTCDateTime(Carbon::now());
        $operations = [];
        foreach ($stations as $id => $row) {
            $id = (string) $id; // numeric ids become int array keys
            $lat = AemetStationData::number($row['lat'] ?? null);
            $lng = AemetStationData::number($row['lon'] ?? null);

            $insert = ['stationId' => $id, 'location' => $row['ubi'] ?? null, 'place' => null,
                'altitude' => AemetStationData::number($row['alt'] ?? null)];
            if ($lat !== null && $lng !== null) {
                $insert += ['coordinates' => [$lng, $lat], 'peninsula' => AemetStationData::peninsulaAt($lat, $lng)];
            }

            $operations[] = ['updateOne' => [
                ['stationId' => $id],
                ['$set' => ['lastSeenAt' => $now], '$setOnInsert' => $insert],
                ['upsert' => true],
            ]];
        }

        AemetStation::raw(fn ($collection) => $collection->bulkWrite($operations, ['ordered' => false]));
    }

    private function reject(string $reason): void
    {
        Log::warning('[UpdateAemetObservations] '.$reason);
        $this->failureReason = $reason;
        $this->healthMessage = AemetFeedHealth::recordFailure('observations', $reason);
    }
}

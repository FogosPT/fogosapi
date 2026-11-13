<?php

namespace App\Jobs;

use App\Models\AemetWarningMessage;
use App\Models\AemetWarningZone;
use App\Support\Aemet\AemetWarningItems;
use App\Support\Aemet\AemetZones;
use App\Support\Aemet\CapMessage;
use App\Support\Aemet\CapTar;
use App\Support\AemetFeedHealth;
use App\Support\WeatherWarningSync;
use App\Tools\AemetException;
use App\Tools\AemetTool;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\UTCDateTime;

/**
 * Imports the AEMET (Spain) warnings: the `avisos_cap` tar is a full snapshot of
 * the CAP messages in force. Each run:
 *  - archives every message not stored yet in `aemetWarningMessages` (never rewritten);
 *  - upserts every area of every message (verde included) in `aemetWarningZones`;
 *  - reconciles `aemetWarnings` with the snapshot through the IPMA engine
 *    (WeatherWarningSync::aemet()), which writes `aemetWarningEvents`.
 * A failed download, a tar with any unreadable file or an empty tar changes no
 * warning (only the endings are recorded) and counts as a failure in
 * AemetFeedHealth, as does a snapshot older than the stored rows or than the last
 * reconciled one (`LAST_SNAPSHOT_KEY`, so a cached tar can't undo an all-clear) or a partial
 * one (fewer distinct zones, verde included, than half the zones already known;
 * it is still archived and its zones upserted). A tar whose messages are all
 * verde or expired is a valid all-clear: the active warnings are withdrawn.
 * A 401 is announced at once (AemetFeedHealth::recordUnauthorized).
 * Does nothing without `AEMET_API_KEY`.
 */
class ProcessAemetWarnings extends Job implements ShouldBeUnique
{
    /** Unix time of the newest `sent` of the last reconciled snapshot (all-clears included). */
    public const LAST_SNAPSHOT_KEY = 'aemet:warnings:lastSnapshot';

    /** Below the redis `retry_after` (90 s). */
    public $timeout = 80;

    /** Lock released when the job ends; this only bounds a crashed worker. */
    public $uniqueFor = 900;

    /** The health message posted by this run, if any (tests). */
    public ?string $healthMessage = null;

    /** Why this run failed (download, unreadable/empty/partial tar, older snapshot); null on success. */
    public ?string $failureReason = null;

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
            Log::debug('[ProcessAemetWarnings] AEMET_API_KEY not set, skipping.');

            return;
        }

        try {
            $messages = CapTar::messages(($this->tool ?? new AemetTool())->warningsTar());
        } catch (\RuntimeException $e) {
            // AemetException (download) or a tar with any unreadable entry.
            if ($e instanceof AemetException && $e->unauthorized) {
                $this->unauthorizedMessage = AemetFeedHealth::recordUnauthorized('warnings', $e->getMessage());
            }
            $this->reject($e->getMessage());

            return;
        }

        if ($messages === []) {
            $this->reject('snapshot vazio');

            return;
        }

        // Counted before the upsert below adds this tar's zones.
        $knownZones = $this->knownZoneCount();

        $this->archive($messages);
        $this->zones($messages);

        // The tar is authoritative (whatever it lacks is withdrawn), so one covering
        // fewer than half the zones we know is taken as truncated, not as an all-clear.
        // The upsert above only adds/refreshes zones, never removes one.
        $tarZones = $this->zoneCount($messages);
        if ($knownZones !== null && $knownZones > 0 && $tarZones * 2 < $knownZones) {
            $this->reject("snapshot parcial: {$tarZones} de {$knownZones} zonas");

            return;
        }

        // The newest `sent` of the whole tar (verde included) dates the snapshot, so a
        // tar with no warning in force is a valid all-clear that withdraws the active ones.
        $snapshotSent = max(array_map(fn (CapMessage $m) => $m->sent->getTimestamp(), $messages));

        // After an all-clear no active row is left to date the stored state, so the
        // engine would take a cached older tar and recreate the withdrawn warnings.
        $lastSnapshot = Cache::get(self::LAST_SNAPSHOT_KEY);
        if ($lastSnapshot !== null && $snapshotSent < (int) $lastSnapshot) {
            $this->reject('snapshot antigo');

            return;
        }

        $diff = WeatherWarningSync::aemet()->sync(
            AemetWarningItems::fromMessages($messages, Carbon::now()),
            Carbon::createFromTimestampUTC($snapshotSent)->toIso8601String(),
        );

        if ($diff === null) {
            $this->failureReason = 'snapshot antigo';
            Log::warning('[ProcessAemetWarnings] '.$this->failureReason);
            $this->healthMessage = AemetFeedHealth::recordFailure('warnings', $this->failureReason);

            return;
        }

        Cache::forever(self::LAST_SNAPSHOT_KEY, $snapshotSent);
        $this->healthMessage = AemetFeedHealth::recordSuccess('warnings');
    }

    /** No reconciliation: only the endings of the stored warnings are recorded. */
    private function reject(string $reason): void
    {
        Log::warning('[ProcessAemetWarnings] '.$reason);
        $this->failureReason = $reason;
        $this->healthMessage = AemetFeedHealth::recordFailure('warnings', $reason);
        WeatherWarningSync::aemet()->sync(null);
    }

    /** Zones stored before this run; null when the count failed (then not checked). */
    private function knownZoneCount(): ?int
    {
        try {
            return AemetWarningZone::count();
        } catch (\Throwable $e) {
            Log::warning('[ProcessAemetWarnings] zones not counted: '.$e->getMessage());
            report($e);

            return null;
        }
    }

    /**
     * Distinct zone codes across every message of the tar (verde included).
     *
     * @param CapMessage[] $messages
     */
    private function zoneCount(array $messages): int
    {
        $codes = [];
        foreach ($messages as $message) {
            foreach ($message->areas as $area) {
                $code = (string) $area['code'];
                if ($code !== '') {
                    $codes[$code] = true;
                }
            }
        }

        return count($codes);
    }

    /**
     * Stores the messages not archived yet; a stored message is never rewritten.
     *
     * @param CapMessage[] $messages
     */
    private function archive(array $messages): void
    {
        $byIdentifier = [];
        foreach ($messages as $message) {
            $byIdentifier[$message->identifier] ??= $message;
        }

        try {
            $stored = AemetWarningMessage::whereIn('identifier', array_map('strval', array_keys($byIdentifier)))
                ->pluck('identifier')->flip()->all();
        } catch (\Throwable $e) {
            Log::warning('[ProcessAemetWarnings] messages not archived: '.$e->getMessage());
            report($e);

            return;
        }

        foreach ($byIdentifier as $message) {
            $identifier = $message->identifier;
            if (isset($stored[$identifier])) {
                continue;
            }

            try {
                AemetWarningMessage::create([
                    'identifier' => $message->identifier,
                    'sent'       => $message->sent,
                    'msgType'    => $message->msgType,
                    'references' => array_values($message->references),
                    'level'      => $message->level,
                    'phenomenon' => $message->phenomenon,
                    'zones'      => array_values(array_unique(array_column($message->areas, 'code'))),
                    'onset'      => $message->onset,
                    'expires'    => $message->expires,
                    'raw'        => $message->raw,
                ]);
            } catch (\Throwable $e) {
                // e.g. a concurrent run inserted it first (unique index).
                Log::warning("[ProcessAemetWarnings] message {$identifier} not archived: ".$e->getMessage());
            }
        }
    }

    /**
     * Upserts every area of every message by `code` (one bulk write).
     *
     * @param CapMessage[] $messages
     */
    private function zones(array $messages): void
    {
        $zones = [];
        foreach ($messages as $message) {
            foreach ($message->areas as $area) {
                $code = (string) $area['code'];
                if ($code === '' || isset($zones[$code])) {
                    continue;
                }
                $zones[$code] = $area;
            }
        }

        if ($zones === []) {
            return;
        }

        $now = new UTCDateTime(Carbon::now());
        $operations = [];
        foreach ($zones as $code => $area) {
            $code = (string) $code; // numeric codes become int array keys
            $operations[] = ['updateOne' => [
                ['code' => $code],
                ['$set' => [
                    'code'       => $code,
                    'name'       => $area['name'],
                    'ccaa'       => substr($code, 0, 2),
                    'province'   => substr($code, 2, 2),
                    'coastal'    => AemetZones::isCoastal($code),
                    'peninsula'  => AemetZones::isPeninsula($code),
                    'geometry'   => AemetZones::geometry($area['polygons']),
                    'lastSeenAt' => $now,
                ]],
                ['upsert' => true],
            ]];
        }

        try {
            AemetWarningZone::raw(fn ($collection) => $collection->bulkWrite($operations, ['ordered' => false]));
        } catch (\Throwable $e) {
            Log::warning('[ProcessAemetWarnings] zones not saved: '.$e->getMessage());
            report($e);
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\FlightPosition;
use App\Models\Incident;
use App\Models\TrackedAircraft;
use App\Tools\DiscordTool;
use App\Tools\FacebookTool;
use App\Tools\Fr24Tool;
use App\Tools\NotificationTool;
use App\Tools\TwitterTool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessFR24Planes extends Job
{
    private const LISBON_LAT = 38.7223;
    private const LISBON_LON = -9.1393;
    private const NOTIFY_GAP_MINUTES = 30;
    private const REG_BATCH_SIZE = 100;
    private const BUDGET_GUARD_RATIO = 0.95;

    public bool $force = false;
    public ?string $lastSkipReason = null;
    public int $positionsWritten = 0;

    public function __construct(bool $force = false)
    {
        $this->force = $force;
    }

    public function handle()
    {
        $this->lastSkipReason = null;
        $this->positionsWritten = 0;

        if (!env('FR24_ENABLE')) {
            $this->lastSkipReason = 'FR24_ENABLE is false';
            return;
        }

        if (!$this->force && !$this->isWithinDaylightWindow()) {
            $this->lastSkipReason = 'outside daylight window (sunrise+1h to sunset-1h, Lisbon)';
            return;
        }

        if (!$this->force && !Incident::isActive()->where('aerial', '>', 0)->exists()) {
            $this->lastSkipReason = 'no active incidents with aerial assets';
            return;
        }

        $limit = (float) env('FR24_MONTHLY_CREDIT_LIMIT', 60000);
        $used = Fr24Tool::monthlyCreditsUsed();
        if ($limit > 0 && $used >= $limit * self::BUDGET_GUARD_RATIO) {
            DiscordTool::postError(
                'FR24 monthly credit budget at '.round($used).' / '.round($limit).' — pausing polling.'
            );
            $this->lastSkipReason = sprintf('monthly credit budget reached (%d / %d)', round($used), round($limit));

            return;
        }

        $tracked = TrackedAircraft::where('active', true)->get();
        if ($tracked->isEmpty()) {
            $this->lastSkipReason = 'no active tracked aircraft';
            return;
        }

        $registrations = $tracked
            ->pluck('registration')
            ->filter(fn ($r) => is_string($r) && $r !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($registrations)) {
            return;
        }

        $trackedByIcao = $tracked->keyBy(fn ($a) => strtolower((string) $a->icao));
        $trackedByReg = $tracked->keyBy(fn ($a) => strtoupper((string) $a->registration));

        foreach (array_chunk($registrations, self::REG_BATCH_SIZE) as $batch) {
            $rows = Fr24Tool::livePositionsLight($batch);
            foreach ($rows as $row) {
                $this->persistRow($row, $trackedByIcao, $trackedByReg);
            }
        }
    }

    private function persistRow(array $row, $trackedByIcao, $trackedByReg): void
    {
        $mapped = Fr24Tool::mapToFlightPosition($row);

        if (empty($mapped['icao']) || $mapped['lat'] === null || $mapped['lon'] === null) {
            return;
        }

        $aircraft = $trackedByIcao->get($mapped['icao']);
        if (!$aircraft && !empty($mapped['registration'])) {
            $aircraft = $trackedByReg->get(strtoupper($mapped['registration']));
        }

        $position = FlightPosition::create($mapped);
        $this->positionsWritten++;

        if ($aircraft && $aircraft->notify) {
            $this->maybeNotifyFirstSighting($aircraft, $position);
        }
    }

    private function maybeNotifyFirstSighting(TrackedAircraft $aircraft, FlightPosition $position): void
    {
        $previous = FlightPosition::where('icao', $aircraft->icao)
            ->where('_id', '<>', $position->_id)
            ->orderBy('created', 'desc')
            ->first();

        if ($previous && $previous->created
            && $previous->created->diffInMinutes(Carbon::now()) <= self::NOTIFY_GAP_MINUTES) {
            return;
        }

        $message = sprintf(
            '🚁ℹ️ Meio aéreo do DECIR %s - %s - %s com base em %s no radar! #FogosPT ℹ️🚁',
            $aircraft->name ?? $aircraft->registration ?? $aircraft->icao,
            $aircraft->type ?? '',
            $aircraft->registration ?? '',
            $aircraft->base ?? ''
        );

        try {
            TwitterTool::tweet($message);
        } catch (\Throwable $e) {
            Log::error('FR24 plane tweet failed: '.$e->getMessage());
        }

        try {
            FacebookTool::publish($message);
        } catch (\Throwable $e) {
            Log::error('FR24 plane FB publish failed: '.$e->getMessage());
        }

        try {
            NotificationTool::sendPlaneNotification($message);
        } catch (\Throwable $e) {
            Log::error('FR24 plane push failed: '.$e->getMessage());
        }
    }

    private function isWithinDaylightWindow(): bool
    {
        $now = time();
        $info = date_sun_info($now, self::LISBON_LAT, self::LISBON_LON);

        if (!is_array($info) || !is_int($info['sunrise'] ?? null) || !is_int($info['sunset'] ?? null)) {
            return false;
        }

        return $now >= ($info['sunrise'] + 3600) && $now <= ($info['sunset'] - 3600);
    }
}

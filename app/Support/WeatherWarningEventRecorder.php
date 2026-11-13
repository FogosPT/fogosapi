<?php

namespace App\Support;

use App\Models\BaseWeatherWarning;
use App\Models\BaseWeatherWarningEvent;
use App\Models\WeatherWarning;
use App\Models\WeatherWarningEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Turns a WeatherWarningDiff into event rows (IPMA: WeatherWarningEvent). A failed write is
 * logged and skipped: the event log must never break the warnings sync.
 */
class WeatherWarningEventRecorder
{
    /** A warning dropped from the feed this close to its end has ended, not been withdrawn. */
    public const END_GRACE_MINUTES = 30;

    /**
     * Only rows that ended this recently get a `terminado` from recordEnded():
     * rows written before the log existed never had `endedAt`, and must not
     * flood the history with orphan endings (the migration also backfills it).
     */
    public const ENDED_LOOKBACK_HOURS = 24;

    /**
     * @param class-string<BaseWeatherWarning>      $warningClass the warnings whose endings are recorded
     * @param class-string<BaseWeatherWarningEvent> $eventClass   where the events are written
     */
    public function __construct(
        private readonly string $warningClass = WeatherWarning::class,
        private readonly string $eventClass = WeatherWarningEvent::class,
    ) {
    }

    public function record(WeatherWarningDiff $diff, Carbon $now): void
    {
        $occurred = $diff->reportDate ? Carbon::parse($diff->reportDate) : $now;

        foreach ($diff->created as $w) {
            $this->write('emitido', $w, null, $w->level, $occurred, $now, ['warningId' => (string) $w->_id]);
        }

        foreach ($diff->levelChanges as ['old' => $old, 'new' => $new]) {
            $up = (WeatherWarningCollapse::LEVEL_RANK[$new->level] ?? 0) > (WeatherWarningCollapse::LEVEL_RANK[$old->level] ?? 0);
            $this->write($up ? 'agravado' : 'desagravado', $new, $old->level, $new->level, $occurred, $now, [
                'previousEndTime'   => $old->endTime?->timestamp !== $new->endTime?->timestamp ? $old->endTime : null,
                'warningId'         => (string) $new->_id,
                'previousWarningId' => (string) $old->_id,
            ]);
        }

        foreach ($diff->updated as ['warning' => $w, 'previousEndTime' => $previousEnd, 'previousText' => $previousText]) {
            if ($previousEnd !== null) {
                $kind = $w->endTime->greaterThan($previousEnd) ? 'prolongado' : 'encurtado';
                $this->write($kind, $w, $w->level, $w->level, $occurred, $now, [
                    'previousEndTime' => $previousEnd,
                    'warningId'       => (string) $w->_id,
                ]);
            }
            if ($previousText !== null) {
                $this->write('texto_alterado', $w, $w->level, $w->level, $occurred, $now, [
                    'previousText' => $previousText,
                    'warningId'    => (string) $w->_id,
                ]);
            }
        }

        foreach ($diff->removed as $w) {
            if ($w->endTime && $w->endTime->lessThanOrEqualTo($now->copy()->addMinutes(self::END_GRACE_MINUTES))) {
                $this->ended($w, $now);
                continue;
            }
            $this->write('retirado', $w, $w->level, null, $occurred, $now, ['warningId' => (string) $w->_id]);
        }
    }

    /**
     * Rows that reached their endTime while still in force get one
     * `terminado`. Runs on every sync, even when the feed is rejected.
     */
    public function recordEnded(Carbon $now): void
    {
        $rows = $this->warningClass::whereNull('supersededAt')
            ->whereNull('endedAt')
            ->where('endTime', '<', $now)
            ->where('endTime', '>=', $now->copy()->subHours(self::ENDED_LOOKBACK_HOURS))
            ->get();

        foreach ($rows as $w) {
            $this->ended($w, $now);
        }
    }

    private function ended(BaseWeatherWarning $w, Carbon $now): void
    {
        if ($this->write('terminado', $w, $w->level, null, $w->endTime, $now, ['reportDate' => null, 'warningId' => (string) $w->_id])) {
            try {
                $w->endedAt = $now;
                $w->save();
            } catch (\Throwable $e) {
                Log::warning('weather warning endedAt not saved: '.$e->getMessage());
                report($e);
            }
        }
    }

    private function write(string $kind, BaseWeatherWarning $w, ?string $from, ?string $to, Carbon $occurred, Carbon $now, array $extra = []): bool
    {
        try {
            $this->eventClass::create(array_merge([
                'kind'       => $kind,
                'district'   => $w->district,
                'type'       => $w->type,
                'fromLevel'  => $from,
                'toLevel'    => $to,
                'startTime'  => $w->startTime,
                'endTime'    => $w->endTime,
                'text'       => $w->text,
                'reportDate' => $w->reportDate,
                'detectedAt' => $now,
                'occurredAt' => $occurred,
            ], $extra));

            return true;
        } catch (\Throwable $e) {
            Log::warning("weather warning event '{$kind}' not saved: ".$e->getMessage());
            report($e);

            return false;
        }
    }
}

<?php

namespace App\Http\Controllers\Concerns;

use App\Models\BaseWeatherWarningEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET v2/warnings/{source}/history`, shared by every warning source (IPMA:
 * WeatherWarningEvent, AEMET: AemetWarningEvent) so the contract is identical.
 */
trait ServesWarningHistory
{
    private const HISTORY_MAX_DAYS = 31;
    private const HISTORY_DEFAULT_DAYS = 7;
    private const HISTORY_LIMIT = 5000;

    /**
     * Event log of a warning source (see WeatherWarningEventRecorder), newest
     * first by `occurredAt`. `from`/`to` are Lisbon dates (YYYY-MM-DD, whole
     * days) or datetimes; `area`, `type` and `kind` take CSV lists.
     * `texto_alterado` is stored but only returned when asked for in `kind`.
     *
     * @param class-string<BaseWeatherWarningEvent> $eventClass
     */
    protected function warningHistory(Request $request, string $eventClass): JsonResponse
    {
        $from = $this->historyDate($request->query('from'), false);
        $to   = $this->historyDate($request->query('to'), true);
        if ($from === false || $to === false) {
            return new JsonResponse(['error' => 'invalid_date'], 422);
        }

        $to   ??= Carbon::now();
        $from ??= $to->copy()->subDays(self::HISTORY_DEFAULT_DAYS);
        if ($from->greaterThan($to)) {
            return new JsonResponse(['error' => 'invalid_date'], 422);
        }
        if ((int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay(), true) > self::HISTORY_MAX_DAYS) {
            return new JsonResponse(['error' => 'range_too_large'], 422);
        }

        $kinds = $this->csv($request->query('kind'))
            ?: array_values(array_diff($eventClass::KINDS, ['texto_alterado']));

        $query = $eventClass::where('occurredAt', '>=', $from)
            ->where('occurredAt', '<=', $to)
            ->whereIn('kind', $kinds);
        if ($areas = $this->csv($request->query('area'))) {
            $query->whereIn('district', $areas);
        }
        if ($types = $this->csv($request->query('type'))) {
            $query->whereIn('type', $types);
        }

        $fmt = fn ($v) => $v ? Carbon::parse($v)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s') : null;

        $rows = $query->orderBy('occurredAt', 'desc')->orderBy('_id', 'desc')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->map(fn (BaseWeatherWarningEvent $e) => [
                'id'              => (string) $e->_id,
                'kind'            => $e->kind,
                'area'            => $e->district,
                'type'            => $e->type,
                'fromLevel'       => $e->fromLevel,
                'toLevel'         => $e->toLevel,
                'startTime'       => $fmt($e->startTime),
                'endTime'         => $fmt($e->endTime),
                'previousEndTime' => $fmt($e->previousEndTime),
                'text'            => $e->text,
                'previousText'    => $e->previousText,
                'reportDate'      => $fmt($e->reportDate),
                'occurredAt'      => $fmt($e->occurredAt),
            ])
            ->values();

        return new JsonResponse($rows);
    }

    /** null = not given, false = invalid. */
    private function historyDate(mixed $value, bool $endOfDay): Carbon|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?)?$/', $value)) {
            return false;
        }

        try {
            $date = Carbon::parse($value, config('app.timezone'));
        } catch (\Throwable) {
            return false;
        }

        return strlen($value) === 10 ? ($endOfDay ? $date->endOfDay() : $date->startOfDay()) : $date;
    }

    /** @return string[] */
    private function csv(mixed $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
    }
}

<?php

namespace App\Support;

use App\Models\WeatherWarning;
use Carbon\Carbon;

/**
 * What one HandleWeatherWarnings::sync() run changed, for the event log.
 * Duplicate rows retired silently and republishes that only move
 * startTime/reportDate are not in here.
 */
class WeatherWarningDiff
{
    /**
     * @param WeatherWarning[] $created       new windows with no previous counterpart
     * @param array<int, array{old: WeatherWarning, new: WeatherWarning}> $levelChanges
     *        `old` is a window of another level replaced by `new`: either `new`
     *        was created over it (old superseded, or the previous version of a
     *        row shrunk around it), or `old` was superseded and a kept row of
     *        another level now covers its interval
     * @param array<int, array{warning: WeatherWarning, previousEndTime: ?Carbon, previousText: ?string}> $updated
     *        rows updated in place whose endTime or text changed
     * @param WeatherWarning[] $removed       rows superseded without a replacement
     */
    public function __construct(
        public readonly array $created,
        public readonly array $levelChanges,
        public readonly array $updated,
        public readonly array $removed,
        public readonly ?string $reportDate,
    ) {
    }
}

<?php

namespace App\Models;

/**
 * One change to an IPMA warning, written by WeatherWarningSync::ipma()
 * (via WeatherWarningEventRecorder). Served by /v2/warnings/ipma/history.
 */
class WeatherWarningEvent extends BaseWeatherWarningEvent
{
    protected $table = 'weather_warning_events';
}

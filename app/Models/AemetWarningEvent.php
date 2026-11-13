<?php

namespace App\Models;

/**
 * One change to an AEMET warning, written by the WeatherWarningEventRecorder of
 * WeatherWarningSync::aemet().
 */
class AemetWarningEvent extends BaseWeatherWarningEvent
{
    protected $table = 'aemetWarningEvents';
}

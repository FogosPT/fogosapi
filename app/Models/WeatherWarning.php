<?php


namespace App\Models;

use App\Observers\WeatherWarningObserver;

class WeatherWarning extends BaseWeatherWarning
{
    use WeatherWarningObserver;

    protected $table = 'weather_warnings';
}

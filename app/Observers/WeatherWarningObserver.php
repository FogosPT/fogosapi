<?php

namespace App\Observers;
use App\Jobs\SendWeatherWarningToTelegram;

trait WeatherWarningObserver
{
    protected static function boot()
    {
        parent::boot();

        static::created(function ($warning) {
            if($warning->district === 'STB'){
                dispatch(new SendWeatherWarningToTelegram($warning, env('PS_PROJECT_TELEGRAM_TOKEN'), env('PS_PROJECT_TELEGRAM_CHANNEL')));
            }
        });

        static::updated(function ($warning) {
        });

        static::deleted(function ($warning) {
        });
    }
}

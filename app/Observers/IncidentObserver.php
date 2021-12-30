<?php

namespace App\Observers;

use App\Jobs\HandleNewIncidentEmergenciasSocialMedia;
use App\Jobs\HandleNewIncidentSocialMedia;
use App\Jobs\ProcessICNFFireData;
use App\Jobs\SaveIncidentHistory;
use App\Jobs\SaveIncidentStatusHistory;
use App\Tools\DiscordTool;
use Illuminate\Support\Facades\Log;

trait IncidentObserver
{
    protected static function boot()
    {
        parent::boot();

        static::created(function ($incident) {
            Log::info("Incident Created Event Fire observer: ". json_encode($incident));
            dispatch(new SaveIncidentHistory($incident));
            dispatch(new SaveIncidentStatusHistory($incident));

            if ($incident->isFire) {
                dispatch(new HandleNewIncidentSocialMedia($incident));
                dispatch(new ProcessICNFFireData($incident));
            }

            dispatch(new HandleNewIncidentEmergenciasSocialMedia($incident));

            if ( $incident->naturezaCode === '2409' ) {
                DiscordTool::postAero("🚨 Novo acidente aereo em {$incident->location} 🚨");
            }
        });

        static::updated(function ($incident) {
            //Log::info("Incident updated Event Fire observer: ".$incident);
            dispatch(new SaveIncidentStatusHistory($incident));
            dispatch(new SaveIncidentHistory($incident));
        });

        static::deleted(function ($incident) {
            //Log::info("Incident deleted Event Fire observer: ".$incident);
        });
    }
}

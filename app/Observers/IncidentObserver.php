<?php

namespace App\Observers;

use App\Jobs\HandleHJProject;
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

            if ($incident->dateTime->year >= 2022) {
                dispatch(new SaveIncidentHistory($incident));
                dispatch(new SaveIncidentStatusHistory($incident));

                if ($incident->isFire) {
                    dispatch(new HandleNewIncidentSocialMedia($incident));
                    dispatch(new ProcessICNFFireData($incident));
                }

                dispatch(new HandleNewIncidentEmergenciasSocialMedia($incident));

                if ($incident->naturezaCode === '2409') {
                    DiscordTool::postAero("🚨 Novo acidente aereo em {$incident->location} 🚨");
                }
            }

            $hlDico1 = explode(',', env('HL_PROJECT_TELEGRAM_CHANNEL_1_DICOS'));
            $hlDico2 = explode(',', env('HL_PROJECT_TELEGRAM_CHANNEL_2_DICOS'));
            $hlDico3 = explode(',', env('HL_PROJECT_TELEGRAM_CHANNEL_3_DICOS'));

            if (in_array($incident->dico, $hlDico1)) {
                dispatch(new HandleHJProject($incident, env('HL_PROJECT_TELEGRAM_CHANNEL_1')));
            } elseif (in_array($incident->dico, $hlDico2)) {
                dispatch(new HandleHJProject($incident, env('HL_PROJECT_TELEGRAM_CHANNEL_2')));
            } elseif (in_array($incident->dico, $hlDico3)) {
                dispatch(new HandleHJProject($incident, env('HL_PROJECT_TELEGRAM_CHANNEL_3')));
            }
        });

        static::updated(function ($incident) {
            if ($incident->dateTime->year >= 2022) {
                //Log::info("Incident updated Event Fire observer: ".$incident);
                dispatch(new SaveIncidentStatusHistory($incident));
                dispatch(new SaveIncidentHistory($incident));
            }
        });

        static::deleted(function ($incident) {
            //Log::info("Incident deleted Event Fire observer: ".$incident);
        });
    }
}

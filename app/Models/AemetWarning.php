<?php

namespace App\Models;

/**
 * One AEMET warning window per (zone, type), reconciled by WeatherWarningSync::aemet().
 * `district` holds the AEMET zone code. Deliberately not a WeatherWarning: the IPMA
 * Telegram observer (STB) must not apply.
 */
class AemetWarning extends BaseWeatherWarning
{
    protected $table = 'aemetWarnings';

    /** Copies the AEMET-only fields of an AemetWarningItems item. */
    public function fillExtra(object $item): void
    {
        $this->areaName    = $item->areaName ?? null;
        $this->phenomenon  = $item->phenomenon ?? null;
        $this->parameter   = $item->parameter ?? null;
        $this->probability = $item->probability ?? null;
        $this->identifiers = array_values((array) ($item->identifiers ?? []));
        $this->en          = (array) ($item->en ?? []);
    }
}

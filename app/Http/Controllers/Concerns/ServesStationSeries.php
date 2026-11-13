<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Station series helpers shared by the IPMA (WeatherController) and AEMET
 * (AemetController) station endpoints, so both serve the same fields, defaults
 * and validation.
 */
trait ServesStationSeries
{
    /** Fields of `v2/weather[/aemet]/observations`, in IPMA's feed order (-99 when missing). */
    private const FEED_OBSERVATION_FIELDS = [
        'intensidadeVentoKM',
        'temperatura',
        'radiacao',
        'idDireccVento',
        'precAcumulada',
        'intensidadeVento',
        'humidade',
        'pressao',
    ];

    private const OBSERVATION_FIELDS = [
        'temperatura',
        'humidade',
        'pressao',
        'precAcumulada',
        'intensidadeVento',
        'intensidadeVentoKM',
        'idDireccVento',
        'radiacao',
    ];

    private const DAILY_FIELDS = [
        'temp_min',
        'temp_med',
        'temp_max',
        'hum_min',
        'hum_med',
        'hum_max',
        'prec_quant',
        'prec_max_inst',
        'rad_total',
        'pressao',
        'vento_int_med',
        'vento_int_max_inst',
        'vento_dir_max',
        'idDireccVento',
    ];

    /**
     * Integer query param within [min, max]; default when absent, null when invalid.
     */
    private function boundedInt(Request $request, string $key, int $default, int $min, int $max): ?int
    {
        if (!$request->has($key)) {
            return $default;
        }

        $value = filter_var($request->query($key), FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            return null;
        }

        return $value;
    }

    /**
     * Selected numeric fields; missing readings (null or IPMA's -99) become null.
     *
     * @param mixed $row
     */
    private function pick($row, array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $v = $row->{$f} ?? null;
            $out[$f] = ($v === null || (float) $v === -99.0) ? null : $v;
        }

        return $out;
    }
}

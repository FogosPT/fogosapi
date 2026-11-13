<?php

namespace App\Support\Aemet;

/**
 * Normalizes AEMET station data to the IPMA field names and units, so the AEMET
 * station endpoints can serve it like the IPMA ones.
 *
 * - Inventory coordinates are DMS strings (`"394924N"`, `"025309E"` = DDMMSS + hemisphere).
 * - Daily summaries carry numbers as strings with a decimal comma (`"17,6"`),
 *   `prec` may be `"Ip"` (inappreciable → 0.0) or `"Acum"` (accumulated with the
 *   next days → null), times may be `"Varias"`, `dir` is in tens of degrees with
 *   99/88 = no direction / variable, `velmedia`/`racha` are in m/s.
 * - IPMA stores the hourly `intensidadeVento` in m/s (and `intensidadeVentoKM` in
 *   km/h) but the daily `vento_int_med`/`vento_int_max_inst` in **km/h**, so the
 *   daily AEMET speeds are converted × 3.6.
 * - `idDireccVento` follows IPMA: 0 calm, 1 N, 2 NE, 3 E, 4 SE, 5 S, 6 SW, 7 W, 8 NW
 *   (45° sectors centred on each direction).
 * A missing value is null; the -99 convention is the endpoints' business.
 */
class AemetStationData
{
    /** Provinces (as the inventory spells them) outside the Iberian Peninsula. */
    private const NON_PENINSULA_PROVINCES = [
        'ILLES BALEARS',
        'BALEARES',
        'LAS PALMAS',
        'SANTA CRUZ DE TENERIFE',
        'STA. CRUZ DE TENERIFE',
        'CEUTA',
        'MELILLA',
    ];

    public const DAILY_FIELDS = [
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

    /** `"394924N"` → 39.8233 (4 decimals); W/S are negative; anything malformed → null. */
    public static function dms(string $v): ?float
    {
        if (!preg_match('/^(\d{2,3})(\d{2})(\d{2})([NSEW])$/', trim($v), $m)) {
            return null;
        }

        [, $deg, $min, $sec, $hemisphere] = $m;
        if ((int) $min >= 60 || (int) $sec >= 60) {
            return null;
        }

        $decimal = round((int) $deg + (int) $min / 60 + (int) $sec / 3600, 4);

        return in_array($hemisphere, ['S', 'W'], true) ? -$decimal : $decimal;
    }

    /** `"17,6"` → 17.6, `"Ip"` → 0.0, `"Acum"`/`"Varias"`/`""`/null → null, numbers pass through as float. */
    public static function number(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }

        if (!is_string($v)) {
            return null;
        }

        $v = trim($v);
        if ($v === 'Ip') {
            return 0.0;
        }

        $v = str_replace(',', '.', $v);

        return is_numeric($v) ? (float) $v : null;
    }

    /** IPMA wind sector of `$deg`: 0 when `$speed` is 0, null without a direction. */
    public static function windSector(?float $deg, ?float $speed): ?int
    {
        if ($speed !== null && $speed == 0.0) {
            return 0;
        }

        if ($deg === null) {
            return null;
        }

        $deg = fmod(fmod($deg, 360.0) + 360.0, 360.0);

        return (int) floor(fmod($deg + 22.5, 360.0) / 45.0) + 1;
    }

    /**
     * One row of `observacion/convencional/todas` in the IPMA hourly fields
     * (`radiacao` does not exist at AEMET and is left out).
     */
    public static function hourly(array $row): array
    {
        $speed = self::number($row['vv'] ?? null);

        return [
            'temperatura'        => self::number($row['ta'] ?? null),
            'humidade'           => self::number($row['hr'] ?? null),
            'pressao'            => self::number($row['pres'] ?? null), // station pressure, as IPMA
            'precAcumulada'      => self::number($row['prec'] ?? null),
            'intensidadeVento'   => $speed,
            'intensidadeVentoKM' => $speed === null ? null : round($speed * 3.6, 1),
            'idDireccVento'      => self::windSector(self::number($row['dv'] ?? null), $speed),
        ];
    }

    /** One row of `valores/climatologicos/diarios` in the IPMA `DAILY_FIELDS`. */
    public static function daily(array $row): array
    {
        $dir = self::number($row['dir'] ?? null);
        $dirDeg = ($dir === null || in_array((int) $dir, [88, 99], true)) ? null : $dir * 10;

        $gust = self::number($row['racha'] ?? null);
        $presMax = self::number($row['presMax'] ?? null);
        $presMin = self::number($row['presMin'] ?? null);

        return [
            'temp_min'           => self::number($row['tmin'] ?? null),
            'temp_med'           => self::number($row['tmed'] ?? null),
            'temp_max'           => self::number($row['tmax'] ?? null),
            'hum_min'            => self::number($row['hrMin'] ?? null),
            'hum_med'            => self::number($row['hrMedia'] ?? null),
            'hum_max'            => self::number($row['hrMax'] ?? null),
            'prec_quant'         => self::number($row['prec'] ?? null),
            'prec_max_inst'      => null,
            'rad_total'          => null,
            'pressao'            => ($presMax === null || $presMin === null) ? null : round(($presMax + $presMin) / 2, 1),
            'vento_int_med'      => self::kmh(self::number($row['velmedia'] ?? null)),
            'vento_int_max_inst' => self::kmh($gust),
            'vento_dir_max'      => $dirDeg,
            'idDireccVento'      => self::windSector($dirDeg, $gust),
        ];
    }

    /** Whether an inventory province is on the Iberian Peninsula. */
    public static function peninsula(string $province): bool
    {
        return !in_array(mb_strtoupper(trim($province)), self::NON_PENINSULA_PROVINCES, true);
    }

    /**
     * Coarse fallback for a station seen only in the observations (no province):
     * south of 36° N is Canarias/Ceuta/Melilla (Tarifa, the peninsula's southern
     * tip, is at 36.01°), and east of 1.1° E between 38.6° and 40.2° N is the Balearic Islands.
     */
    public static function peninsulaAt(float $lat, float $lng): bool
    {
        if ($lat < 36.0) {
            return false;
        }

        return !($lng > 1.1 && $lat > 38.6 && $lat < 40.2);
    }

    private static function kmh(?float $ms): ?float
    {
        return $ms === null ? null : round($ms * 3.6, 1);
    }
}

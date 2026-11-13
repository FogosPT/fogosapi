<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesWarningHistory;
use App\Models\AemetWarning;
use App\Models\AemetWarningEvent;
use App\Models\AemetWarningZone;
use App\Support\Aemet\AemetZones;
use App\Support\WeatherWarningCollapse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * AEMET (Spain) warnings, normalized to the IPMA endpoints: same shape, keys,
 * formats, parameters and errors as `v2/warnings/ipma[/history]`, plus
 * AEMET-only fields. `idAreaAviso` / `area` are AEMET zone codes.
 */
class AemetController extends Controller
{
    use ServesWarningHistory;

    /** `v2/warnings/aemet[?peninsula=1]` — like `v2/warnings/ipma`. */
    public function warnings(Request $request): JsonResponse
    {
        $peninsula = $request->boolean('peninsula');

        return new JsonResponse(Cache::remember('aemet:warnings:' . (int) $peninsula, 60, function () use ($peninsula) {
            $warnings = AemetWarning::whereNull('supersededAt')->where('endTime', '>=', Carbon::now())->get();
            if ($peninsula) {
                $warnings = $warnings->filter(fn (AemetWarning $w) => AemetZones::isPeninsula((string) $w->district))->values();
            }

            return WeatherWarningCollapse::collapse($warnings, fn (AemetWarning $w) => [
                'areaName'    => $w->areaName,
                'phenomenon'  => $w->phenomenon,
                'parameter'   => $w->parameter,
                'probability' => $w->probability,
            ]);
        }));
    }

    /** `v2/warnings/aemet/history` — like `v2/warnings/ipma/history`. */
    public function history(Request $request): JsonResponse
    {
        return $this->warningHistory($request, AemetWarningEvent::class);
    }

    /** `v2/warnings/aemet/zones[?peninsula=1]` — GeoJSON FeatureCollection. */
    public function zones(Request $request): JsonResponse
    {
        $peninsula = $request->boolean('peninsula');

        return new JsonResponse(Cache::remember('aemet:zones:' . (int) $peninsula, 3600, function () use ($peninsula) {
            $features = AemetWarningZone::orderBy('code')->get()
                ->filter(fn (AemetWarningZone $z) => !$peninsula || AemetZones::isPeninsula((string) $z->code))
                ->map(fn (AemetWarningZone $z) => [
                    'type'       => 'Feature',
                    'geometry'   => $z->geometry,
                    'properties' => [
                        'code'      => $z->code,
                        'name'      => $z->name,
                        'ccaa'      => $z->ccaa,
                        'province'  => $z->province,
                        'coastal'   => (bool) $z->coastal,
                        'peninsula' => (bool) $z->peninsula,
                    ],
                ])
                ->values()
                ->all();

            return ['type' => 'FeatureCollection', 'features' => $features];
        }));
    }
}

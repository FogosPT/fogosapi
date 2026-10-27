<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRCM;
use App\Models\RCM;
use App\Models\RCMForJS;
use App\Tools\RCMTool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use voku\helper\UTF8;


class RCMController extends Controller

{
    private const DAY_TO_WHEN = [
        0 => 'hoje',
        1 => 'amanha',
        2 => 'depois',
        3 => 'depois2',
        4 => 'depois3',
    ];

    public function update(){Bus::dispatchNow(new ProcessRCM(false,false));}

    public function ipma(int $day)
    {
        $when = self::DAY_TO_WHEN[$day] ?? null;
        if (!$when) {
            abort(404);
        }

        $cacheKey = 'rcm:ipma:d' . $day;
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return new JsonResponse(json_decode($cached, true));
        }

        $risk = RCMForJS::where('when', $when)
            ->orderBy('created', 'desc')
            ->first();

        if (!$risk) {
            abort(404);
        }

        $payload = [
            'dataPrev' => $risk->dataPrev,
            'dataRun'  => $risk->dataRun,
            'fileDate' => $risk->fileDate,
            'local'    => $risk->local,
        ];

        Redis::set($cacheKey, json_encode($payload), 'EX', 600);

        return new JsonResponse($payload);
    }


    public function today()
    {
        return $this->geoJsonFor('hoje');
    }

    public function tomorrow()
    {
        return $this->geoJsonFor('amanha');
    }

    public function after()
    {
        return $this->geoJsonFor('depois');
    }

    /**
     * Risk map as GeoJSON. Building it decodes a ~340 KB template and walks
     * every feature, so the result is cached until ProcessRCM stores a new map
     * (it forgets these keys) or for 15 minutes.
     */
    private function geoJsonFor(string $when): JsonResponse
    {
        $geoJson = Cache::remember(self::geoJsonCacheKey($when), 900, function () use ($when) {
            $risk = RCMForJS::where('when', $when)
                ->orderBy('created', 'desc')
                ->first();

            return $risk ? RCMTool::buildGeoJSON($risk->toArray()['local']) : null;
        });

        if ($geoJson === null) {
            abort(404);
        }

        return response()->json($geoJson);
    }

    public static function geoJsonCacheKey(string $when): string
    {
        return 'rcm.geojson.v1.'.$when;
    }

    public function parish(Request $request)
    {
        $concelho = $request->get('concelho');
        $concelho = UTF8::ucwords(UTF8::strtolower(trim($concelho)));

        $risk = RCM::where('concelho', $concelho)
            ->orderBy('created', 'desc')
            ->limit(1)
            ->get();

        if ($risk->isEmpty()) {
            abort(404);
        }

        $responseRisk = [
            'today' => $risk[0]['hoje'],
            'tomorrow' => $risk[0]['amanha'],
            'after' => $risk[0]['depois'],
        ];

        $response = [
            'success' => true,
            'data' => $responseRisk,
        ];

        return response()->json($response);
    }
}

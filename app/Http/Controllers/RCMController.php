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
        $risk = RCMForJS::where('when', 'hoje')
            ->orderBy('created', 'desc')
            ->limit(1)
            ->get();

        if ($risk->isEmpty()) {
            abort(404);
        }

        $risk = $risk[0]->toArray();

        $dicos = $risk['local'];

        $geoJson = RCMTool::buildGeoJSON($dicos);

        return response()->json($geoJson);
    }

    public function tomorrow()
    {
        $risk = RCMForJS::where('when', 'amanha')
            ->orderBy('created', 'desc')
            ->limit(1)
            ->get();

        if ($risk->isEmpty()) {
            abort(404);
        }

        $risk = $risk[0]->toArray();

        $dicos = $risk['local'];

        $geoJson = RCMTool::buildGeoJSON($dicos);

        return response()->json($geoJson);
    }

    public function after()
    {
        $risk = RCMForJS::where('when', 'depois')
            ->orderBy('created', 'desc')
            ->limit(1)
            ->get();

        if ($risk->isEmpty()) {
            abort(404);
        }

        $risk = $risk[0]->toArray();

        $dicos = $risk['local'];

        $geoJson = RCMTool::buildGeoJSON($dicos);

        return response()->json($geoJson);
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

<?php

namespace App\Http\Controllers;

use App\Models\RCM;
use App\Tools\RCMTool;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\RCMForJS;
use voku\helper\UTF8;
use App\Jobs\ProcessRCM;


class RCMController extends Controller

{
    
	public function update(){dispatch_now(new ProcessRCM(false,false));}


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

        if (!isset($risk[0])) {
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

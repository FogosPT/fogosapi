<?php

namespace App\Http\Controllers;

use App\Tools\RCMTool;
use Laravel\Lumen\Routing\Controller;
use App\Models\RCMForJS;

class RCMController extends Controller
{
    public function today()
    {
        $risk = RCMForJS::where('when', 'hoje')
            ->orderBy('created', 'desc')
            ->limit(1)
            ->get();

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

        $risk = $risk[0]->toArray();

        $dicos = $risk['local'];

        $geoJson = RCMTool::buildGeoJSON($dicos);

        return response()->json($geoJson);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Planes;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class PlanesController extends Controller
{
    public function icao($icao)
    {
        $plane = Planes::where('icao', $icao)
            ->where('lat', '<>', '')
            ->orderBy('created', 'desc')
            ->limit(20)
            ->get();

        return new JsonResponse([
            'success' => true,
            'data' => $plane,
        ]);
    }
}

<?php


namespace App\Http\Controllers;

use App\Models\Planes;
use Illuminate\Http\JsonResponse;
use Laravel\Lumen\Routing\Controller;

class PlanesController extends Controller
{
    public function icao($icao)
    {
        $plane = Planes::where('icao', $icao)
            ->where('lat', '<>', '')
            ->orderBy('created', 'desc')
            ->limit(10)
            ->get();

        return new JsonResponse([
            'success' => true,
            'data' =>$plane,
        ]);
    }
}

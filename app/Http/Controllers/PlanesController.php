<?php


namespace App\Http\Controllers;

use App\Models\Planes;
use Illuminate\Http\JsonResponse;
use Laravel\Lumen\Routing\Controller;
use Nyholm\Psr7\Request;

class PlanesController extends Controller
{
    public function icao(Request $request, $icao)
    {
        $plane = Planes::where('icao', $icao)
            ->orderBy('created', 'desc')
            ->limit(10)
            ->get();

        return new JsonResponse([
            'success' => true,
            'data' =>$plane,
        ]);
    }
}

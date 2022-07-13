<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Resources\IncidentResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller;

class StatsController extends Controller
{
    public function ignitionsHourly()
    {
        $timestamp = Carbon::today()->startOfDay();

        $incidents = Incident::where('created', '>', $timestamp)
                        ->where('isFire', true)
                        ->get();

        $hours = [];

        foreach ($incidents as $i){
            $k = (string)$i->created->hour;
            if(isset($hours[$k])){
                $hours[$k]++;
            } else {
                $hours[$k] = 1;
            }
        }

        return new JsonResponse([
            'success' => true,
            'data' => $hours,
        ]);
    }
}

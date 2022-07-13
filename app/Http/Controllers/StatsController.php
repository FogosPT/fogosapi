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
            if(isset($hours[(string)$i->created->hour])){
                $hours[(string)$i->created->hour]++;
            } else {
                $hours[(string)$i->created->hour] = 1;
            }
        }

        return new JsonResponse([
            'success' => true,
            'data' => $hours,
        ]);
    }
}

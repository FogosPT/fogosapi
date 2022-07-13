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
    public function ignitionsHourly(Request $request)
    {
        $day = $request->get('day');

        if($day){
            $day = new Carbon($day);
        } else {
            $day = Carbon::today();
        }


        $incidents = Incident::whereBetween(
                        'dateTime',
                        [
                            Carbon::parse($day->startOfDay()),
                            Carbon::parse($day->endOfDay())
                        ]
                        )
                        ->where('isFire', true)
                        ->get();

        $hours = [];

        foreach ($incidents as $i){
            $k = $i->dateTime->startOfHour()->hour . 'H' . ' - ' . ($i->dateTime->startOfHour()->hour + 1) . 'H';
            if(isset($hours[$k])){
                $hours[$k]++;
            } else {
                $hours[$k] = 1;
            }
        }
        ksort($hours);

        return new JsonResponse([
            'success' => true,
            'data' => $hours,
        ]);
    }
}

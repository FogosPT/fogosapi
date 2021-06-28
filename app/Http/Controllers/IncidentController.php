<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Resources\IncidentResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller;

class IncidentController extends Controller
{
    public function active(Request $request): JsonResponse
    {
        $all = $request->get('all');

        if($all){
            $incidents = Incident::isActive()->get();

        } else {
            $incidents = Incident::isActive()->isFire()->get();
        }

        return new JsonResponse([
            'success' => true,
            'data' => IncidentResource::collection($incidents),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        //@todo validate date format
        $day = $request->get('day');
        if(!$day){
            abort(422, 'day is required');
        }

        $date = new Carbon($day);
        $all = $request->get('all');
        $concelho = $request->get('concelho');

        $incidents = Incident::whereBetween(
                'dateTime',
                array(
                    Carbon::parse($date->startOfDay()),
                    Carbon::parse($date->endOfDay())
                )
            );

        if($concelho){
            $incidents = $incidents->where('concelho', $concelho);
        }

        if(!$all){
            $incidents = $incidents->isFire();
        }

        $incidents = $incidents->get();

        return new JsonResponse([
            'success' => true,
            'data' => IncidentResource::collection($incidents),
        ]);
    }
}

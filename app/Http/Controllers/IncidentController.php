<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncidentSearchRequest;
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

        $incidents = Incident::isActive()
                            ->when(!$all, function ($query, $all){
                                return $query->isFire();
                            })
                            ->get();

        return new JsonResponse([
            'success' => true,
            'data' => IncidentResource::collection($incidents),
        ]);
    }

    public function search(IncidentSearchRequest $request): JsonResponse
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
                [
                    Carbon::parse($date->startOfDay()),
                    Carbon::parse($date->endOfDay())
                ]
            )
            ->when($concelho, function($query, $concelho){
                return $query->where('concelho', $concelho);
            })
            ->when(!$all, function ($query, $all){
                return $query->isFire();
            })
            ->get();

        return new JsonResponse([
            'success' => true,
            'data' => IncidentResource::collection($incidents),
        ]);
    }
}

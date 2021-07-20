<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncidentSearchRequest;
use App\Models\Incident;
use App\Resources\IncidentResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $day = $request->get('day');
        $before = $request->get('before');
        $after = $request->get('after');

        if($day && ($before || $after)){
            abort(422);
        }

        $timeRange = false;
        if($after){
            $after = new Carbon($after);
            $before = $before ? new Carbon($before) : new Carbon();
            $timeRange = [$after, $before];
        }

        if($day){
            $day = new Carbon($day);
        }

        $all = $request->get('all');
        $extend = $request->get('extend');
        $concelho = $request->get('concelho');


        $incidents = Incident::when($day, function($query, $day){
            return $query->whereBetween(
                'dateTime',

                [
                    Carbon::parse($day->startOfDay()),
                    Carbon::parse($day->endOfDay())
                ]
            );
        })->when($timeRange, function($query,$timeRange){
            return $query->whereBetween(
                'dateTime',
                [
                    Carbon::parse($timeRange[0]->startOfDay()),
                    Carbon::parse($timeRange[1]->endOfDay())
                ]
            );
        })->when($concelho, function($query, $concelho){
            return $query->where('concelho', $concelho);
        })->when(!$all, function ($query, $all){
            return $query->isFire();
        })->when($extend, function ($query, $extend){
            return $query->with(['history', 'statusHistory']);
        })
        ->get();

        return new JsonResponse([
            'success' => true,
            'data' => IncidentResource::collection($incidents),
        ]);
    }

    public function kml(Request $request, $id)
    {
        $incident = Incident::where('id', $id)->get()[0];
        $incidentkml = $incident->kml;

        $response = new StreamedResponse();
        $response->setCallBack(function () use($incidentkml) {
            echo $incidentkml;
        });
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $incident->id . '.kml');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}

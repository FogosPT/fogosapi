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

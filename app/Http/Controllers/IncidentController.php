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
        $isFMA = $request->get('fma');
        $isOtherFire = $request->get('otherfire');
        $concelho = $request->get('concelho');

        if($request->exists('limit')){
            $limit = (int)$request->get('limit');
        } else {
            $limit = 300;
        }

        $geoJson = $request->get('geojson');

        $incidents = Incident::isActive()
                            ->when(!$all, function ($query, $all){
                                return $query->isFire();
                            })->when($isFMA, function ($query, $isFMA){
                                return $query->isFMA();
                            })->when($isOtherFire, function ($query, $isOtherFire){
                                return $query->isOtherFire();
                            })->when($concelho, function ($query, $concelho){
                                return $query->where('concelho', $concelho);
                            })
                            ->orderBy('created_at', 'desc')
                            ->paginate($limit);

        if($geoJson){
            return new JsonResponse($this->transformToGeoJSON(IncidentResource::collection($incidents)));
        } else {
            return new JsonResponse([
                'success' => true,
                'data' => IncidentResource::collection($incidents),
            ]);
        }
    }

    public function activeKML(Request $request): JsonResponse
    {
        $all = $request->get('all');
        $isFMA = $request->get('fma');
        $isOtherFire = $request->get('otherfire');
        $concelho = $request->get('concelho');

        if($request->exists('limit')){
            $limit = (int)$request->get('limit');
        } else {
            $limit = 300;
        }

        $geoJson = $request->get('geojson');

        $incidents = Incident::isActive()
            ->when(!$all, function ($query, $all){
                return $query->isFire();
            })->when($isFMA, function ($query, $isFMA){
                return $query->isFMA();
            })->when($isOtherFire, function ($query, $isOtherFire){
                return $query->isOtherFire();
            })->when($concelho, function ($query, $concelho){
                return $query->where('concelho', $concelho);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        $r=new \XMLWriter();
        $r->openMemory();
        $r->startDocument('1.0','UTF-8');
        $r->startElement('kml');
        $r->startElement('document');
        $r->startElement('Placemark');
        foreach($incidents as $i){
            $r->startElement('name');
            $r->text($i['location']);
            $r->endElement();
            $r->startElement('description');
            $r->text($i['natureza']);
            $r->endElement();
            $r->startElement('Point');
            $r->startElement('coordinates');
            $r->text($i['lng'].' , '.$i['lat']);
            $r->endElement(); // coordinates
            $r->endElement(); // point
        }

        $r->endElement(); // Placemark
        $r->endElement(); // document
        $r->endElement(); // kml
        $newxml = $r->outputMemory(true);


        echo $newxml;
        die();
    }

    private function transformToGeoJSON($data)
    {
        foreach($data as $d) {
            $features[] = array(
                'type' => 'Feature',
                'geometry' => array('type' => 'Point', 'coordinates' => array((float)$d['lng'],(float)$d['lat'])),
                'properties' => $d,
            );
        };

        $allfeatures = array('type' => 'FeatureCollection', 'features' => $features);

        return $allfeatures;
    }

    public function search(IncidentSearchRequest $request): JsonResponse
    {
        $day = $request->get('day');
        $before = $request->get('before');
        $after = $request->get('after');

        if($request->exists('limit')){
            $limit = (int)$request->get('limit');
        } else {
            $limit = 50;
        }

        if($day && ($before || $after)){
            abort(422);
        }

        $timeRange = false;
        if($after){
            $after = new Carbon($after);
            $before = new Carbon($before);
            $timeRange = [$after, $before];
        }

        if($day){
            $day = new Carbon($day);
        }

        $all = $request->get('all');
        $isFMA = $request->get('fma');
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
        })->when($isFMA, function ($query, $isFMA){
            return $query->isFMA();
        })
        ->paginate($limit);

        $paginator = [
            'currentPage' => $incidents->currentPage(),
            'totalPages' => $incidents->lastPage(),
            'totalItems' => $incidents->total()
        ];

        return new JsonResponse([
            'success' => true,
            'paginator' => $paginator,
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

    public function addPosit(Request $request, $id)
    {
        $key = $request->header('key');

        if(env('API_WRITE_KEY') !== $key){
            abort(401);
        }

        $incident = Incident::where('id', $id)->get()[0];

        $incident->extra = $request->post('posit') . ' ' . $incident->extra;

        $incident->save();

        return new JsonResponse([
            'success' => true,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncidentSearchRequest;
use App\Models\Incident;
use App\Resources\IncidentResource;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\ScreenShotTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        $geoJson = filter_var($request->get('geojson'), FILTER_VALIDATE_BOOLEAN);;

        $csv = $request->get('csv');
        $csv2 = $request->get('csv2');

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

        if($csv) {
            $csv = 'incendios.csv';

            header('Content-Disposition: attachment; filename="' . $csv . '";');
            header('Content-Type: application/csv; charset=UTF-8');

            // open the "output" stream
            $f = fopen('php://output', 'w');
            // Write utf-8 bom to the file
            fputs($f, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($f, array_keys($incidents[0]->toArray()), ';');


            foreach ($incidents as $i) {
                $_i = $i->toArray();
                unset($_i['icnf']);
                unset($_i['coordinates']);
                fputcsv($f, $_i, ';');
            }


            // use exit to get rid of unexpected output afterward
            exit();
        } else if($csv2){
            $csv = 'incendios.csv';

            header('Content-Disposition: attachment; filename="' . $csv . '";');
            header('Content-Type: application/csv; charset=UTF-8');

            // open the "output" stream
            $f = fopen('php://output', 'w');
            // Write utf-8 bom to the file
            fputs($f, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if(empty($incidents)){
                Log::debug(json_encode($incidents));

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
            }

            $arr = IncidentResource::collection($incidents)->resolve();

            $keys = $arr[0];
            unset($keys['_id']);
            unset($keys['dateTime']);
            unset($keys['created']);
            unset($keys['updated']);
            unset($keys['icnf']);
            unset($keys['coordinates']);

            fputcsv($f, array_keys($keys), ';');


            foreach ($arr as &$i) {
                $_i = $i;
                unset($_i['_id']);
                unset($_i['dateTime']);
                unset($_i['created']);
                unset($_i['updated']);
                unset($_i['icnf']);
                unset($_i['coordinates']);
                $_i['kml'] = null;
                $_i['extra'] = null;
                fputcsv($f, $_i, ';');
            }

            // use exit to get rid of unexpected output afterward
            exit();
        } else if($geoJson){
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

        $incident->extra = $request->post('posit');

        $incident->save();

        return new JsonResponse([
            'success' => true,
        ]);
    }

    public function addKML(Request $request, $id)
    {
        $key = $request->header('key');

        if(env('API_WRITE_KEY') !== $key){
            abort(401);
        }

        $incident = Incident::where('id', $id)->get()[0];

        $incident->kml = $request->post('kml');

        $incident->save();

        $url = "fogo/{$incident->id}/detalhe?aasd=" .  rand(0,255);
        $name = "screenshot-{$incident->id}"  . rand(0,255);
        $path = "/var/www/html/public/screenshots/{$name}.png";

        $status = $request->post('status');

        if($status)
        {
            ScreenShotTool::takeScreenShot($url, $name, 1200, 450);


            TwitterTool::tweet($status, false, $path, false, true);

            ScreenShotTool::removeScreenShotFile($name);
        } else {
            ScreenShotTool::takeScreenShot($url, $name, 1200, 450);

            $domain = env('SOCIAL_LINK_DOMAIN');
            $hashTag = HashTagTool::getHashTag($incident->concelho);

            $status = "🗺 Nova área de interesse por @VostPT  https://{$domain}/fogo/{$incident->id} {$hashTag} 🗺";

            $lastId = TwitterTool::tweet($status, $incident->lastTweetId, $path, false, true);
            ScreenShotTool::removeScreenShotFile($name);

            $incident->lastTweetId = $lastId;
            $incident->save();
        }

        return new JsonResponse([
            'success' => true,
        ]);
    }
}

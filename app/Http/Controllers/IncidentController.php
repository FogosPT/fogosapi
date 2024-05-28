<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncidentSearchRequest;
use App\Models\Incident;
use App\Resources\IncidentResource;
use App\Tools\HashTagTool;
use App\Tools\ScreenShotTool;
use App\Tools\TwitterTool;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        if ($request->exists('limit')) {
            $limit = (int) $request->get('limit');
        } else {
            $limit = 300;
        }

        $geoJson = filter_var($request->get('geojson'), FILTER_VALIDATE_BOOLEAN);

        $csv = $request->get('csv');
        $csv2 = $request->get('csv2');

        $subRegion = $request->get('subRegion');

        $incidents = Incident::isActive()
            ->when(! $all, function ($query, $all) {
                return $query->isFire();
            })->when($isFMA, function ($query, $isFMA) {
                return $query->isFMA();
            })->when($isOtherFire, function ($query, $isOtherFire) {
                return $query->isOtherFire();
            })->when($concelho, function ($query, $concelho) {
                return $query->where('concelho', $concelho);
            })->when($subRegion, function ($query, $subRegion) {
                return $query->where('sub_regiao', $subRegion);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        if ($csv) {
            $csv = 'incendios.csv';

            header('Content-Disposition: attachment; filename="'.$csv.'";');
            header('Content-Type: application/csv; charset=UTF-8');

            // open the "output" stream
            $f = fopen('php://output', 'w');
            // Write utf-8 bom to the file
            fwrite($f, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($f, array_keys($incidents[0]->toArray()), ';');

            foreach ($incidents as $i) {
                $_i = $i->toArray();
                unset($_i['icnf']);
                unset($_i['coordinates']);
                fputcsv($f, $_i, ';');
            }

            // use exit to get rid of unexpected output afterward
            exit();
        } elseif ($csv2) {
            $csv = 'incendios.csv';

            header('Content-Disposition: attachment; filename="'.$csv.'";');
            header('Content-Type: application/csv; charset=UTF-8');

            // open the "output" stream
            $f = fopen('php://output', 'w');
            // Write utf-8 bom to the file
            fwrite($f, chr(0xEF).chr(0xBB).chr(0xBF));

            if (empty($incidents)) {
                Log::debug(json_encode($incidents));

                $incidents = Incident::isActive()
                    ->when(! $all, function ($query, $all) {
                        return $query->isFire();
                    })->when($isFMA, function ($query, $isFMA) {
                        return $query->isFMA();
                    })->when($isOtherFire, function ($query, $isOtherFire) {
                        return $query->isOtherFire();
                    })->when($concelho, function ($query, $concelho) {
                        return $query->where('concelho', $concelho);
                    })
                    ->orderBy('created_at', 'desc')
                    ->paginate($limit);
            }

            $arr = IncidentResource::collection($incidents)->resolve();

            if (isset($arr[0])) {
                $keys = $arr[0];
                unset($keys['_id']);
                unset($keys['dateTime']);
                unset($keys['created']);
                unset($keys['updated']);
                unset($keys['icnf']);
                unset($keys['coordinates']);
                unset($keys['kmlVost']);

                fputcsv($f, array_keys($keys), ';');

                foreach ($arr as &$i) {
                    $_i = $i;
                    unset($_i['_id']);
                    unset($_i['dateTime']);
                    unset($_i['created']);
                    unset($_i['updated']);
                    unset($_i['icnf']);
                    unset($_i['coordinates']);
                    unset($_i['kmlVost']);
                    $_i['kml'] = null;
                    $_i['extra'] = null;
                    fputcsv($f, $_i, ';');
                }
            } else {
                $incident = Incident::isFire()
                    ->orderBy('created_at', 'desc')
                    ->paginate(1);

                $arr = IncidentResource::collection($incident)->resolve();

                $keys = $arr[0];
                unset($keys['_id']);
                unset($keys['dateTime']);
                unset($keys['created']);
                unset($keys['updated']);
                unset($keys['icnf']);
                unset($keys['coordinates']);
                unset($keys['kmlVost']);

                fputcsv($f, array_keys($keys), ';');

            }
            // use exit to get rid of unexpected output afterward
            exit();
        } elseif ($geoJson) {
            return new JsonResponse($this->transformToGeoJSON(IncidentResource::collection($incidents)));
        } else {

            if (env('TROLL_MODE')) {
                $ua = $request->userAgent();
                $ref = $request->headers->get('referer');

                $allowedUas = [
                    env('UA1'),
                    env('UA2'),
                    env('UA3'),
                ];

                $allowedRefs = [
                    'https://www.fogos.pt/',
                    'https://fogos.pt/',
                    'https://beta.fogos.pt/',
                    'https://emergencias.pt/',
                    'https://www.emergencias.pt/',
                    'https://sgmai.maps.arcgis.com/apps/dashboards/fc641a97229142b8a80f17af034d62a7',
                ];

                if (! in_array($ua, $allowedUas) || ! in_array($ref, $allowedRefs)) {
                    $troll = new Incident();
                    $troll->id = 123123123123;
                    $troll->coords = 1;
                    $troll->dateTime = '2023-05-17T06:38:00.000000Z';
                    $troll->date = '17-05-2023';
                    $troll->hour = '07:38';
                    $troll->location = 'Uso a API do Fogos.pt 🥁';
                    $troll->aerial = 100;
                    $troll->meios_aquaticos = 100;
                    $troll->man = 25486;
                    $troll->terrain = 48765;
                    $troll->district = 'Fogos.pt';
                    $troll->concelho = 'Fogos.pt';
                    $troll->freguesia = 'Fogos.pt';
                    $troll->dico = 213;
                    $troll->lat = 37.95588;
                    $troll->lng = -7.271392;
                    $troll->naturezaCode = 4512;
                    $troll->natureza = 'Utilização indevida';
                    $troll->especieName = 'Utilização indevida';
                    $troll->familiaName = 'Utilização indevida';
                    $troll->statusCode = 5;
                    $troll->statusColor = 'B81E1F';
                    $troll->status = 'Em Curso';
                    $troll->important = false;
                    $troll->localidade = 'Uso a API do Fogos.pt 🥁';
                    $troll->active = true;
                    $troll->sadoId = 123123123;
                    $troll->sharepointId = 123123123;
                    $troll->extra = $ua.' => '.$ref;
                    $troll->disappear = false;
                    $troll->created = '2023-05-18T07:58:09.600000Z';
                    $troll->updated = '2023-05-18T07:58:09.600000Z';

                    $incidents[] = $troll;
                }
            }

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

        if ($request->exists('limit')) {
            $limit = (int) $request->get('limit');
        } else {
            $limit = 300;
        }

        $geoJson = $request->get('geojson');

        $incidents = Incident::isActive()
            ->when(! $all, function ($query, $all) {
                return $query->isFire();
            })->when($isFMA, function ($query, $isFMA) {
                return $query->isFMA();
            })->when($isOtherFire, function ($query, $isOtherFire) {
                return $query->isOtherFire();
            })->when($concelho, function ($query, $concelho) {
                return $query->where('concelho', $concelho);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        $r = new \XMLWriter();
        $r->openMemory();
        $r->startDocument('1.0', 'UTF-8');
        $r->startElement('kml');
        $r->startElement('document');
        $r->startElement('Placemark');
        foreach ($incidents as $i) {
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
        exit();
    }

    private function transformToGeoJSON($data)
    {
        $features = [];

        foreach ($data as $d) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [(float) $d['lng'], (float) $d['lat']]],
                'properties' => $d,
            ];
        }

        $allfeatures = ['type' => 'FeatureCollection', 'features' => $features];

        return $allfeatures;
    }

    public function search(IncidentSearchRequest $request)
    {
        $day = $request->get('day');
        $before = $request->get('before');
        $after = $request->get('after');

        $naturezaCode = $request->get('naturezaCode');

        if ($request->exists('limit')) {
            $limit = (int) $request->get('limit');
        } else {
            $limit = 50;
        }

        if ($day && ($before || $after)) {
            abort(422);
        }

        $timeRange = false;
        if ($after) {
            $after = new Carbon($after);
            $before = new Carbon($before);
            $timeRange = [$after, $before];
        }

        if ($day) {
            $day = new Carbon($day);
        }

        $all = $request->get('all');
        $isFMA = $request->get('fma');
        $extend = $request->get('extend');
        $concelho = $request->get('concelho');

        $subRegion = $request->get('subRegion');

        $incidents = Incident::when($day, function ($query, $day) {
            return $query->whereBetween(
                'dateTime',

                [
                    Carbon::parse($day->startOfDay()),
                    Carbon::parse($day->endOfDay()),
                ]
            );
        })->when($timeRange, function ($query, $timeRange) {
            return $query->whereBetween(
                'dateTime',
                [
                    Carbon::parse($timeRange[0]->startOfDay()),
                    Carbon::parse($timeRange[1]->endOfDay()),
                ]
            );
        })->when($concelho, function ($query, $concelho) {
            return $query->where('concelho', $concelho);
        })->when(! $all, function ($query, $all) {
            return $query->isFire();
        })->when($extend, function ($query, $extend) {
            return $query->with(['history', 'statusHistory']);
        })->when($isFMA, function ($query, $isFMA) {
            return $query->isFMA();
        })->when($naturezaCode, function ($query, $naturezaCode) {
            return $query->where('naturezaCode', (string) $naturezaCode);
        })->when($subRegion, function ($query, $subRegion) {
            return $query->where('sub_regiao', (string) $subRegion);
        });

        $csv2 = $request->get('csv2');

        if ($csv2) {
            $csv = 'incidents.csv';

            header('Content-Disposition: attachment; filename="'.$csv.'";');
            header('Content-Type: application/csv; charset=UTF-8');

            // open the "output" stream
            $f = fopen('php://output', 'w');
            // Write utf-8 bom to the file
            fwrite($f, chr(0xEF).chr(0xBB).chr(0xBF));

            $incidents = $incidents->get();
            $arr = IncidentResource::collection($incidents)->resolve();

            if (isset($arr[0])) {
                $keys = $arr[0];
                unset($keys['_id']);
                unset($keys['dateTime']);
                unset($keys['created']);
                unset($keys['updated']);
                unset($keys['icnf']);
                unset($keys['coordinates']);
                unset($keys['kmlVost']);

                fputcsv($f, array_keys($keys), ';');

                foreach ($arr as &$i) {
                    $_i = $i;
                    unset($_i['_id']);
                    unset($_i['dateTime']);
                    unset($_i['created']);
                    unset($_i['updated']);
                    unset($_i['icnf']);
                    unset($_i['coordinates']);
                    unset($_i['kmlVost']);
                    $_i['kml'] = null;
                    $_i['extra'] = null;
                    fputcsv($f, $_i, ';');
                }
            }
        } else {
            $incidents = $incidents->paginate($limit);

            $paginator = [
                'currentPage' => $incidents->currentPage(),
                'totalPages' => $incidents->lastPage(),
                'totalItems' => $incidents->total(),
            ];

            return new JsonResponse([
                'success' => true,
                'paginator' => $paginator,
                'data' => IncidentResource::collection($incidents),
            ]);
        }
    }

    public function kml(Request $request, $id)
    {
        $incident = Incident::where('id', $id)->get()[0];
        $incidentkml = $incident->kml;

        $response = new StreamedResponse();
        $response->setCallBack(function () use ($incidentkml) {
            echo $incidentkml;
        });
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $incident->id.'.kml');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    public function kmlVost(Request $request, $id)
    {
        $incident = Incident::where('id', $id)->get()[0];
        $incidentkml = $incident->kmlVost;

        $response = new StreamedResponse();
        $response->setCallBack(function () use ($incidentkml) {
            echo $incidentkml;
        });
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $incident->id.'.kml');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    public function burnMoreThan1000(Request $request)
    {
        if ($request->exists('limit')) {
            $limit = (int) $request->get('limit');
        } else {
            $limit = 500;
        }

        $incidents = Incident::where('isFire', true)
            ->where('icnf.burnArea.total', '>', 1000)
            ->paginate($limit);

        $paginator = [
            'currentPage' => $incidents->currentPage(),
            'totalPages' => $incidents->lastPage(),
            'totalItems' => $incidents->total(),
        ];

        return new JsonResponse([
            'success' => true,
            'paginator' => $paginator,
            'data' => IncidentResource::collection($incidents),
        ]);
    }

    public function addPosit(Request $request, $id)
    {
        $key = $request->header('key');

        if (env('API_WRITE_KEY') !== $key) {
            abort(401);
        }

        $incident = Incident::where('id', $id)->get()[0];

        $incident->extra = $request->post('posit');
        $incident->pco = $request->post('pco');
        $incident->cos = $request->post('cos');

        $incident->save();

        return new JsonResponse([
            'success' => true,
        ]);
    }

    public function addKML(Request $request, $id)
    {
        $key = $request->header('key');

        if (env('API_WRITE_KEY') !== $key) {
            abort(401);
        }

        $incident = Incident::where('id', $id)->get()[0];

        $incident->kmlVost = $request->post('kml');

        $incident->save();

        $url = "fogo/{$incident->id}/detalhe?aasd=".rand(0, 255);
        $name = "screenshot-{$incident->id}".rand(0, 255);
        $path = "/var/www/html/public/screenshots/{$name}.png";

        $status = $request->post('status');

        if ($status) {
            ScreenShotTool::takeScreenShot($url, $name, 1200, 450);

            TwitterTool::tweet($status, false, $path, false, true);

            ScreenShotTool::removeScreenShotFile($name);
        } else {
            ScreenShotTool::takeScreenShot($url, $name, 1200, 550);

            $domain = env('SOCIAL_LINK_DOMAIN');
            $hashTag = HashTagTool::getHashTag($incident->concelho);

            $status = "🗺 Nova área de interesse por @VostPT  https://{$domain}/fogo/{$incident->id}/detalhe {$hashTag} 🗺";

            $lastId = TwitterTool::tweet($status, $incident->lastTweetId, $path, false, false);
            ScreenShotTool::removeScreenShotFile($name);

            $incident->lastTweetId = $lastId;
            $incident->save();
        }

        return new JsonResponse([
            'success' => true,
        ]);
    }
}

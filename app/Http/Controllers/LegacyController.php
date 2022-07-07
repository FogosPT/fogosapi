<?php

namespace App\Http\Controllers;

use App\Models\HistoryTotal;
use App\Models\Incident;
use App\Models\IncidentHistory;
use App\Models\IncidentStatusHistory;
use App\Models\RCM;
use App\Models\RCMForJS;
use App\Models\Warning;
use App\Models\WarningMadeira;
use App\Models\WarningSite;
use App\Resources\IncidentResource;
use App\Resources\V1\HistoryStatusResource;
use App\Resources\V1\HistoryTotalResource;
use App\Resources\V1\WarningResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller;
use voku\helper\UTF8;

/** @deprecated */
class LegacyController extends Controller
{
    public function newFires(Request $request): JsonResponse
    {
        $concelho = $request->get('concelho');
        $distrito = $request->get('distrito');

        $incidents = Incident::isActive()->isFire()->when($concelho, function($query, $concelho){
            return $query->where('concelho', $concelho);
        })->when($distrito, function($query, $distrito){
            return $query->where('district', $distrito);
        })->get();

        return new JsonResponse([
            'success' => true,
            'data' => IncidentResource::collection($incidents),
        ]);
    }

    public function firesData(Request $request)
    {
        $id = $request->get('id');

        $incident = Incident::where('id', $id)->get();

        if (isset($incident[0])) {
            $incident = $incident[0];
        } else {
            abort(404);
        }

        $history = IncidentHistory::where('id', $id)
            ->orderBy('created', 'asc')
            ->limit(200)
            ->get();

        $return = [];
        $first = [
            'label' => $incident['hour'],
            'man' => 0,
            'terrain' => 0,
            'aerial' => 0,
            'created' => $incident['dateTime']->timestamp,
        ];

        foreach ($history as &$r) {
            $timestamp = $r['created']->timestamp;
            $label = date('d-m-Y H:i', $r['created']->timestamp);
            unset($r['updated']);
            $r['label'] = $label;
            $r['created'] = $timestamp;

            $_r = [
                'created' => $r['created']->timestamp,
                'label' => $label,
                'man' => $r['man'],
                'terrain' => $r['terrain'],
                'aerial' => $r['aerial'],
                'location' => $r['location'],
                'id' => $r['id'],
                '_id' => $r['_id'],
            ];

            $return[] = $_r;
        }

        $return = array_reverse($return);

        $return[] = $first;

        $response = [
            'success' => true,
            'data' => array_reverse($return),
        ];

        return response()->json($response);
    }

    public function fires(Request $request)
    {
        $id = $request->get('id');

        $incident = Incident::where('id', $id)->get();

        if (isset($incident[0])) {

            return new JsonResponse([
                'success' => true,
                'data' => IncidentResource::collection($incident)[0],
            ]);
        }

        abort(404);
    }

    public function warnings() : JsonResponse
    {
        $warnings = Warning::orderBy('created', 'desc')
            ->limit(50)
            ->get();

        return new JsonResponse([
            'success' => true,
            'data' => WarningResource::collection($warnings),
        ]);
    }

    public function warningsSite()
    {
        $warnings = WarningSite::orderBy('created', 'desc')
            ->limit(50)
            ->get();

        $response = [
            'success' => true,
            'data' => $warnings,
        ];

        return response()->json($response);
    }

    public function warningsMadeira()
    {
        $warnings = WarningMadeira::orderBy('created', 'desc')
            ->limit(50)
            ->get();

        $data = [];
        foreach ($warnings as $warning) {
            $label = date('d-m-Y H:i', strtotime($warning['created']));

            $warning['label'] = $label;

            $data[] = $warning;
        }

        $response = [
            'success' => true,
            'data' => $data,
        ];

        return response()->json($response);
    }

    public function statsWeek()
    {
        $timestampLast = strtotime(date('Y-m-d 00:00'));

        $return = [];
        for ($i = 0; $i <= 8; ++$i) {
            $start = strtotime("-{$i} days", $timestampLast);
            $date_start = Carbon::parse($start)->startOfDay();
            $date_end = Carbon::parse($start)->endOfDay();

            $incidents = Incident::where('isFire', true)->where([['dateTime', '>=', $date_start], ['dateTime', '<=', $date_end]]);

            $_r = [
                'label' => $date_start->format('Y-m-d', ),
                'total' => $incidents->count(),
            ];
            $return[] = $_r;
        }

        $return = array_reverse($return);

        $response = [
            'success' => true,
            'data' => $return,
        ];

        return response()->json($response);
    }

    public function statsToday()
    {
        $date_start = Carbon::today()->startOfDay();
        $date_end = Carbon::today()->endOfDay();

        $data = $this->getForStatsTimestamp($date_start, $date_end);

        $response = [
            'success' => true,
            'data' => $data,
        ];

        return response()->json($response);
    }

    public function statsYesterday()
    {
        $date_start = Carbon::yesterday()->startOfDay();
        $date_end = Carbon::yesterday()->endOfDay();

        $data = $this->getForStatsTimestamp($date_start, $date_end);

        $response = [
            'success' => true,
            'data' => $data,
        ];

        return response()->json($response);
    }


    public function firesDanger(Request $request)
    {
        $id = $request->get('id');

        $incident = Incident::where('id', $id)->get();

        if (isset($incident[0])) {
            $rcm = RCM::where('concelho', $incident[0]->concelho)
                ->limit(1)
                ->orderBy('created', 'desc')
                ->get();


            if (isset($rcm[0])) {
                $rcm = $rcm[0];
                $created = $rcm->created;
                $updated = $rcm->updated;
                $rcm = $rcm->toArray();
                $rcm['created'] = ['sec' => $created->getTimestamp()];
                $rcm['updated'] = ['sec' => $updated->getTimestamp()];

                $response = [
                    'success' => true,
                    'data' => [$rcm],
                ];

                return response()->json($response);
            }
            abort(404);
        } else {
            abort(404);
        }
    }

    public function firesStatus(Request $request)
    {
        $id = $request->get('id');

        $incident = Incident::where('id', $id)->get();

        if (isset($incident[0])) {
            $incident = $incident[0];
            $statusHistory = IncidentStatusHistory::where('id', $id)
                ->orderBy('created', 'desc')
                ->get();

            $first = [
                'label' => $incident['date'].' '.$incident['hour'],
                'status' => 'Início',
                'statusCode' => 99,
                'created' =>  strtotime($incident['dateTime']),
            ];

            $data = HistoryStatusResource::collection($statusHistory)->toArray($request);

            $data[] = $first;

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        }
        abort(404);
    }

    public function firesMadeira(Request $request)
    {
        return response()->json();
    }

    public function firesStatusMadeira(Request $request)
    {
        return response()->json();
    }

    public function now()
    {
        $incidents = Incident::where('active', true)
            ->where('isFire', true)
            ->whereIn('statusCode', Incident::ACTIVE_STATUS_CODES);

        $date = date('H:i');
        $total = $incidents->count();

        $man = 0;
        $areal = 0;
        $cars = 0;
        foreach ($incidents->get() as $f) {
            $man += $f['man'];
            $areal += $f['aerial'];
            $cars += $f['terrain'];
        }

        $data = [
            'man' => $man,
            'aerial' => $areal,
            'cars' => $cars,
            'total' => $total,
            'date' => $date,
        ];

        $response = [
            'success' => true,
            'data' => array_reverse($data),
        ];

        return response()->json($response);
    }

    public function nowData()
    {
        $data = HistoryTotal::orderBy('created', 'desc')->limit(50)->get();

        return new JsonResponse([
            'success' => true,
            'data' => array_reverse(HistoryTotalResource::collection($data)),
        ]);

        return response()->json($response);
    }

    private function getForStatsTimestamp($start, $end)
    {
        $incidents = Incident::where('isFire', true)
            ->where([['dateTime', '>=', $start], ['dateTime', '<=', $end]])
            ->get();

        $total = $incidents->count();
        $distritos = [];
        foreach ($incidents as $r) {
            if (!isset($distritos[$r['district']])) {
                $distritos[$r['district']] = 1;
            } else {
                ++$distritos[$r['district']];
            }
        }

        arsort($distritos);

        return [
            'total' => $total,
            'distritos' => $distritos,
        ];
    }

    private function getForBurnedArea($start, $end)
    {
        $incidents = Incident::where('isFire', true)
            ->where('icnf.burnArea.total', '>', 0)
            ->where([['dateTime', '>=', $start], ['dateTime', '<=', $end]])
            ->get();

        $total = 0;

        foreach ($incidents as $r) {
            $total += (float)$r['icnf']['burnArea']['total'];
        }

        return $total;
    }

    public function stats8hours()
    {
        $data = [];
        $h = date('H');
        if ($h >= 8) {
            $timestampLast = Carbon::today();
            $timestamp = Carbon::today()->addHours(8);

            $data['00h-08h'] = $this->getForStatsTimestamp($timestampLast, $timestamp);
        }

        if ($h >= 16) {
            $timestampLast = Carbon::today()->addHours(8);
            $timestamp = Carbon::today()->addHours(16);

            $data['08h-16h'] = $this->getForStatsTimestamp($timestampLast, $timestamp);
        }

        $response = [
            'success' => true,
            'data' => $data,
        ];

        return response()->json($response);
    }

    public function stats8hoursYesterday()
    {
        $data = [];

        $timestampLast = Carbon::yesterday();
        $timestamp = Carbon::yesterday()->addHours(8);
        $data['00h-08h'] = $this->getForStatsTimestamp($timestampLast, $timestamp);

        $timestampLast = Carbon::yesterday()->addHours(8);
        $timestamp = Carbon::yesterday()->addHours(16);
        $data['08h-16h'] = $this->getForStatsTimestamp($timestampLast, $timestamp);

        $timestampLast = Carbon::yesterday()->addHours(16);
        $timestamp = Carbon::yesterday()->addHours(24);
        $data['16h-24h'] = $this->getForStatsTimestamp($timestampLast, $timestamp);

        $response = [
            'success' => true,
            'data' => $data,
        ];

        return response()->json($response);
    }

    public function burnedAreaLastDays()
    {
        $data = [];

        $timestampLast = Carbon::today();
        $timestamp = Carbon::today()->endOfDay();
        $data[$timestampLast->format('d-m-Y')] = $this->getForBurnedArea($timestampLast, $timestamp);

        $timestampLast = Carbon::yesterday();
        $timestamp = Carbon::yesterday()->endOfDay();
        $data[$timestampLast->format('d-m-Y')] = $this->getForBurnedArea($timestampLast, $timestamp);

        $timestampLast = Carbon::yesterday()->subDays(1);
        $timestamp = Carbon::yesterday()->subDays(1)->endOfDay();
        $data[$timestampLast->format('d-m-Y')] = $this->getForBurnedArea($timestampLast, $timestamp);

        $timestampLast = Carbon::yesterday()->subDays(2);
        $timestamp = Carbon::yesterday()->subDays(2)->endOfDay();
        $data[$timestampLast->format('d-m-Y')] = $this->getForBurnedArea($timestampLast, $timestamp);

        $timestampLast = Carbon::yesterday()->subDays(3);
        $timestamp = Carbon::yesterday()->subDays(3)->endOfDay();
        $data[$timestampLast->format('d-m-Y')] = $this->getForBurnedArea($timestampLast, $timestamp);

        $timestampLast = Carbon::yesterday()->subDays(4);
        $timestamp = Carbon::yesterday()->subDays(4)->endOfDay();
        $data[$timestampLast->format('d-m-Y')] = $this->getForBurnedArea($timestampLast, $timestamp);

        $timestampLast = Carbon::yesterday()->subDays(5);
        $timestamp = Carbon::yesterday()->subDays(5)->endOfDay();
        $data[$timestampLast->format('d-m-Y')] = $this->getForBurnedArea($timestampLast, $timestamp);

        $timestampLast = Carbon::yesterday()->subDays(6);
        $timestamp = Carbon::yesterday()->subDays(6)->endOfDay();
        $data[$timestampLast->format('d-m-Y')] = $this->getForBurnedArea($timestampLast, $timestamp);

        $response = [
            'success' => true,
            'data' => array_reverse($data),
        ];

        return response()->json($response);
    }

    public function lastNight()
    {
        $timestampLast = Carbon::yesterday()->addHours(21);
        $timestamp = Carbon::today()->addHours(9);

        $response = [
            'success' => true,
            'data' => $this->getForStatsTimestamp($timestampLast, $timestamp),
        ];

        return response()->json($response);
    }

    public function status()
    {
        $active = $incidents = Incident::where('active', true)
            ->where('isFire', true)
            ->whereIn('statusCode', Incident::ACTIVE_STATUS_CODES)
            ->get();

        $date = date('H:i');
        if (empty($active)) {
            $status = "{$date} - Sem registo de incêndios ativos. https://fogos.pt #FogosPT #Status";
        } else {
            $total = count($active);
            $man = 0;
            $areal = 0;
            $cars = 0;
            foreach ($active as $f) {
                $man += $f['man'];
                $areal += $f['aerial'];
                $cars += $f['terrain'];
            }

            $status = "{$date} - {$total} Incêndios em curso combatidos por {$man} operacionais, {$cars} meios terrestres e {$areal} meios aereos. https://fogos.pt #FogosPT";
        }

        $response = [
            'success' => true,
            'data' => $status,
        ];

        return response()->json($response);
    }

    public function active()
    {
        $active = Incident::where('active', true)
            ->where('isFire', true)
            ->whereIn('statusCode', Incident::ACTIVE_STATUS_CODES)
            ->get();

        $response = [
            'success' => true,
            'data' => $active,
        ];

        return response()->json($response);
    }

    public function aerial()
    {
        $active = $incidents = Incident::where('active', true)
            ->where('isFire', true)
            ->whereIn('statusCode', Incident::ACTIVE_STATUS_CODES)
            ->get();

        $date = date('H:i');
        if (empty($active)) {
            $status = "{$date} - Sem registo de incêndios ativos. https://fogos.pt #FogosPT #Status";
        } else {
            $distritos = [];
            foreach ($active as $r) {
                if (!isset($distritos[$r['district']])) {
                    $distritos[$r['district']] = [
                        'm' => $r['aerial'],
                        't' => 1,
                    ];
                } else {
                    $distritos[$r['district']]['m'] += $r['aerial'];
                    ++$distritos[$r['district']]['t'];
                }
            }
            $status = "Distrito - Meios aéreos / incêndios ativos:\r\n";
            foreach ($distritos as $d => $k) {
                $status .= "{$d} - {$k['m']}/{$k['t']}\r\n";
            }
        }

        $response = [
            'success' => true,
            'data' => $status,
        ];

        return response()->json($response);
    }

    public function stats()
    {
        $timestampLast = Carbon::today();
        $timestamp = Carbon::now();

        $result = $this->getForStatsTimestamp($timestampLast, $timestamp);
        $total = $result['total'];
        $distritos = $result['distritos'];

        $status = 'Desde as 00:00 de hoje registamos '.$total." ocorrências de incêndios.\r\n";

        foreach ($distritos as $k => $v) {
            $status .= $k.' - '.$v."\r\n";
        }

        $response = [
            'success' => true,
            'data' => $status,
        ];

        return response()->json($response);
    }

    public function risk(Request $request)
    {
        $concelho = $request->get('concelho');
        $concelho = UTF8::ucwords(UTF8::strtolower(trim($concelho)));

        $risk = RCM::where('concelho', $concelho)
            ->orderBy('created', 'desc')
            ->get();

        if (isset($risk[0])) {
            $status = $concelho." risco de incêndio: \r\n Hoje - ".$risk[0]['hoje'].",\r\n Amanhã - ".$risk[0]['amanha'].",\r\n Depois - ".$risk[0]['depois']."\r\n #FogosPT #Risco";
        } else {
            $status = "\"{$concelho}\" não encontrado :'( https://fogos.pt #FogosPT #Risco";
        }

        $response = [
            'success' => true,
            'data' => $status,
        ];

        return response()->json($response);
    }

    public function riskToday()
    {
        $risk = RCMForJS::where('when', 'hoje')
            ->orderBy('created', 'desc')
            ->limit(1)
            ->get();

        $risk = $risk[0]->toArray();
        unset($risk['created'], $risk['updated'], $risk['_id']);

        $response = [
            'success' => true,
            'data' => $risk,
        ];

        return response()->json($response);
    }

    public function riskTomorrow()
    {
        $risk = RCMForJS::where('when', 'amanha')
            ->orderBy('created', 'desc')
            ->limit(1)
            ->get();

        $risk = $risk[0]->toArray();
        unset($risk['created'], $risk['updated'], $risk['_id']);

        $response = [
            'success' => true,
            'data' => $risk,
        ];

        return response()->json($response);
    }

    public function riskAfter()
    {
        $risk = RCMForJS::where('when', 'depois')
            ->orderBy('created', 'desc')
            ->limit(1)
            ->get();

        $risk = $risk[0]->toArray();
        unset($risk['created'], $risk['updated'], $risk['_id']);

        $response = [
            'success' => true,
            'data' => $risk,
        ];

        return response()->json($response);
    }

    public function listConcelho(Request $request)
    {
        $concelho = $request->get('concelho');
        $concelho = UTF8::ucwords(UTF8::strtolower(trim($concelho)));

        $fires = Incident::where('concelho', $concelho)
            ->where('active', true)
            ->where('isFire', true)
            ->whereIn('statusCode', Incident::ACTIVE_STATUS_CODES)
            ->get();

        if ($fires->count() > 0) {
            foreach ($fires as $f) {
                $status = '';
                $status .= $f['location'].' - MH: '.$f['man'].' MT: '.$f['terrain'].' MA: '.$f['aerial'].' - '.$f['status'].' - '.$f['natureza'].' https://fogos.pt?fire='.$f['id'].' #FogosPT';
            }
        } else {
            $date = date('H:i');
            $status = "{$date} - Sem registo de incêndios ativos em \"{$concelho}\". https://fogos.pt #FogosPT #Status";
        }

        $response = [
            'success' => true,
            'data' => $status,
        ];

        return response()->json($response);
    }
}

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
use App\Tools\TwitterTool;
use Carbon\Carbon;
use Illuminate\Http\Request;
use voku\helper\UTF8;

class LegacyController extends Controller
{
    /**
     * @OA\Get(
     *     tags={"legacy"},
     *     path="/new/fires",
     *     description="Active Fires",
     *     @OA\Response(response="default", description="Active Fires")
     * )
     */
    public function newFires()
    {
        $incidents = Incident::where('active', true)
                            ->where('isFire', true)
                            ->get();

        $response = array(
            'success' => true,
            'data' => $incidents->toArray()
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/fires/data",
     *   tags={"legacy"},
     *   summary="Fire history means",
     *   description="",
     *   @OA\Parameter(
     *     name="id",
     *     required=true,
     *     in="query",
     *     description="FireId",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(response="default", description="Fire history means"),
     *   @OA\Response(response=404, description="not found")
     * )
     */
    public function firesData(Request $request)
    {
        $id = $request->get('id');

        $incident = Incident::where('id', $id)->get();

        if(isset($incident[0])){
            $incident = $incident[0];
        } else {
            abort(404);
        }

        $history = IncidentHistory::where('id', $id)
                    ->orderBy('creadted', 'desc')
                    ->limit(50)
                    ->get();

        $return = array();
        $first = array(
            'label' => $incident['hour'],
            'man' => 0,
            'terrain' => 0,
            'aerial' => 0,
            'created' => $incident['dateTime']->timestamp,
        );

        foreach ($history as &$r) {
            $timestamp = $r['created']->timestamp;
            $label = date('d-m-Y H:i', $r['created']->timestamp);
            unset($r['updated']);
            $r['label'] = $label;
            $r['created'] =$timestamp;

            $_r = array(
                'created' => $r['created']->timestamp,
                'label' => $label,
                'man' => $r['man'],
                'terrain' => $r['terrain'],
                'aerial' => $r['aerial'],
                'location' => $r['location'],
                'id' => $r['id'],
                '_id' => $r['_id']
            );

            $return[] = $_r;
        }

        $return[] = $first;

        $response = array(
            'success' => true,
            'data' => array_reverse($return)
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/fires",
     *   tags={"legacy"},
     *   summary="Fire detail",
     *   description="",
     *   @OA\Parameter(
     *     name="id",
     *     required=true,
     *     in="query",
     *     description="FireId",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(response="default", description="fire id"),
     *   @OA\Response(response=404, description="not found")
     * )
     */
    public function fires(Request $request)
    {
        $id = $request->get('id');

        $incident = Incident::where('id', $id)->get();

        if(isset($incident[0])){
            $response = array(
                'success' => true,
                'data' => $incident[0]
            );

            return response()->json($response);
        } else {
            abort(404);
        }
    }

    /**
     * @OA\Get(path="/v1/warnings",
     *   tags={"legacy"},
     *   summary="Warnings list",
     *   description="",
     *   @OA\Response(response="default", description="Warnings list"),
     * )
     */
    public function warnings()
    {
        $warnings = Warning::orderBy('created', 'desc')
            ->take(50);

        $data = array();
        foreach($warnings as $warning){
            $label = date('d-m-Y H:i', strtotime($warning['created']));

            $warning['label'] = $label;

            $data[] = $warning;
        }

        $response = array(
            'success' => true,
            'data' => $data
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/v1/warnings/site",
     *   tags={"legacy"},
     *   summary="Warnings for site",
     *   description="",
     *   @OA\Response(response="default", description="Warnings for site"),
     * )
     */
    public function warningsSite()
    {
        $warnings = WarningSite::orderBy('created', 'desc')
            ->take(50);

        $response = array(
            'success' => true,
            'data' => $warnings
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/v1/madeira/warnings",
     *   tags={"legacy"},
     *   summary="Madeira Warnings list",
     *   description="",
     *   @OA\Response(response="default", description="Madeira Warnings list"),
     * )
     */
    public function warningsMadeira()
    {
        $warnings = WarningMadeira::orderBy('created', 'desc')
            ->take(50);

        $data = array();
        foreach($warnings as $warning){
            $label = date('d-m-Y H:i', strtotime($warning['created']));

            $warning['label'] = $label;

            $data[] = $warning;
        }

        $response = array(
            'success' => true,
            'data' => $data
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/v1/stats/week",
     *   tags={"legacy"},
     *   summary="Last 7 days total fires",
     *   description="",
     *   @OA\Response(response="default", description="Last 7 days total fires"),
     * )
     */
    public function statsWeek()
    {
        $timestampLast = strtotime(date('Y-m-d 00:00'));

        $return = array();
        for($i = 0; $i <= 8; $i++){
            $start = strtotime("-{$i} days", $timestampLast);
            $date_start = Carbon::parse($start)->startOfDay();
            $date_end = Carbon::parse($start)->endOfDay();

            $incidents = Incident::where('isFire',true)->where([['dateTime','>=',$date_start], ['dateTime','<=',$date_end]]);

            $_r = array(
                'label' => $date_start->format('Y-m-d', ),
                'total' => $incidents->count(),
            );
            $return[] = $_r;
        }

        $return = array_reverse($return);

        $response = array(
            'success' => true,
            'data' => $return
        );

        return response()->json($response);
    }


    /**
     * @OA\Get(path="/fires/danger",
     *   tags={"legacy"},
     *   summary="Fire RCM",
     *   description="",
     *   @OA\Parameter(
     *     name="id",
     *     required=true,
     *     in="query",
     *     description="FireId",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(response="default", description="Fire RCM"),
     *   @OA\Response(response=404, description="not found")
     * )
     */
    public function firesDanger(Request $request)
    {
        $id = $request->get('id');

        $incident = Incident::where('id', $id)->get();

        if(isset($incident[0])){
            $rcm = RCM::where('concelho', $incident[0]->concelho)
                ->limit(1)
                ->orderBy('created', 'desc')
                ->get();

            if(isset($rcm[0])){
                $response = array(
                    'success' => true,
                    'data' => array($rcm[0])
                );

                return response()->json($response);

            } else {
                abort(404);
            }
        } else {
            abort(404);
        }
    }

    /**
     * @OA\Get(path="/fires/status",
     *   tags={"legacy"},
     *   summary="Fire Status History",
     *   description="",
     *   @OA\Parameter(
     *     name="id",
     *     required=true,
     *     in="query",
     *     description="FireId",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(response="default", description="Fire Status History"),
     *   @OA\Response(response=404, description="not found")
     * )
     */
    public function firesStatus(Request $request)
    {
        $id = $request->get('id');

        $incident = Incident::where('id', $id)->get();

        if(isset($incident[0])){
            $incident = $incident[0];
            $statusHistory = IncidentStatusHistory::where('id', $id)
                ->orderBy('created', 'desc')
                ->get()
                ->toArray();

            $first = array(
                'label' => $incident['date'] . ' ' . $incident['hour'],
                'status' => 'Início',
                'statusCode' => 99,
                'created' => $incident['dateTime']
            );

            $data = array();

            foreach($statusHistory as &$history){
                $label = date('d-m-Y H:i', strtotime($history['created']));
                $history['label'] = $label;

                $data[] = $history;
            }

            $data[] = $first;

            $response = array(
                'success' => true,
                'data' => $data
            );

            return response()->json($response);
        } else {
            abort(404);
        }

    }

    /**
     * @OA\Get(path="/madeira/fires",
     *   tags={"legacy"},
     *   summary="NOT WORKING",
     *   description="",
     *   @OA\Parameter(
     *     name="id",
     *     required=true,
     *     in="query",
     *     description="FireId",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(response="default", description="NOT WORKING"),
     *   @OA\Response(response=404, description="not found")
     * )
     */
    public function firesMadeira(Request $request)
    {
        return response()->json();
    }

    /**
     * @OA\Get(path="/madeira/fires/status",
     *   tags={"legacy"},
     *   summary="NOT WORKING",
     *   description="",
     *   @OA\Parameter(
     *     name="id",
     *     required=true,
     *     in="query",
     *     description="FireId",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(response="default", description="NOT WORKING"),
     *   @OA\Response(response=404, description="not found")
     * )
     */
    public function firesStatusMadeira(Request $request)
    {
        return response()->json();
    }

    /**
     * @OA\Get(path="/v1/now",
     *   tags={"legacy"},
     *   summary="Active fires status now",
     *   description="",
     *   @OA\Response(response="default", description="Active fires status now"),
     * )
     */
    public function now()
    {
        $incidents = Incident::where('active', true)
                            ->where('isFire', true)
                            ->whereIn('statusCode', Incident::ACTIVE_STATUS_CODES);

        $date = date("H:i");
        $total = $incidents->count();

        $man = 0;
        $areal = 0;
        $cars = 0;
        foreach($incidents->get() as $f){
            $man += $f['man'];
            $areal += $f['aerial'];
            $cars += $f['terrain'];
        }

        $data = array(
            'man' => $man,
            'aerial' => $areal,
            'cars' => $cars,
            'total' => $total,
            'date' => $date
        );

        $response = array(
            'success' => true,
            'data' => $data
        );

    }

    /**
     * @OA\Get(path="/v1/now/data",
     *   tags={"legacy"},
     *   summary="Last 50 total means history",
     *   description="",
     *   @OA\Response(response="default", description="Last 50 total means history"),
     * )
     */
    public function nowData()
    {
        $data = HistoryTotal::orderBy('created', 'desc')->limit(50)->get();
        $response = array(
            'success' => true,
            'data' => $data
        );

        return response()->json($response);
    }

    private function getForStatsTimestamp($start, $end)
    {
        $incidents = Incident::where('isFire',true)
            ->where([['dateTime','>=',$start], ['dateTime','<=',$end]])
            ->get();

        $total = $incidents->count();
        $distritos = array();
        foreach ($incidents as $r) {
            if( !isset($distritos[$r['district']])){
                $distritos[$r['district']] = 1;
            } else {
                $distritos[$r['district']]++;
            }
        }

        arsort($distritos);

        $return = array(
            'total' => $total,
            'distritos' => $distritos
        );

        return $return;
    }

    /**
     * @OA\Get(path="/v1/stats/8hours",
     *   tags={"legacy"},
     *   summary="Today district stats for 00H to 08H and 08H to 16H",
     *   description="",
     *   @OA\Response(response="default", description="Today district stats for 00H to 08H and 08H to 16H"),
     * )
     */
    public function stats8hours()
    {
        $data = array();
        $h = date('H');
        if( $h >=8 ){
            $timestampLast = Carbon::today();
            $timestamp = Carbon::today()->addHours(8);

            $data['00h-08h'] = $this->getForStatsTimestamp($timestampLast,$timestamp);
        }

        if( $h >=16 ){
            $timestampLast = Carbon::today()->addHours(8);
            $timestamp = Carbon::today()->addHours(16);

            $data['08h-16h'] = $this->getForStatsTimestamp($timestampLast,$timestamp);
        }

        $response = array(
            'success' => true,
            'data' => $data
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/v1/stats/8hours/yesterday",
     *   tags={"legacy"},
     *   summary="Yesterday district stats for 00H to 08H, 08H to 16H and 16H to 24H",
     *   description="",
     *   @OA\Response(response="default", description="Yesterday district stats for 00H to 08H, 08H to 16H and 16H to 24H"),
     * )
     */
    public function stats8hoursYesterday()
    {
        $data = array();

        $timestampLast = Carbon::yesterday();
        $timestamp = Carbon::yesterday()->addHours(8);
        $data['00h-08h'] = $this->getForStatsTimestamp($timestampLast,$timestamp);

        $timestampLast = Carbon::yesterday()->addHours(8);
        $timestamp = Carbon::yesterday()->addHours(16);
        $data['08h-16h'] = $this->getForStatsTimestamp($timestampLast,$timestamp);

        $timestampLast = Carbon::yesterday()->addHours(16);
        $timestamp = Carbon::yesterday()->addHours(24);
        $data['16h-24h'] = $this->getForStatsTimestamp($timestampLast,$timestamp);

        $response = array(
            'success' => true,
            'data' => $data
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/v1/stats/last-night",
     *   tags={"legacy"},
     *   summary="Last night fires (21H to 09H today)",
     *   description="",
     *   @OA\Response(response="default", description="Last night fires (21H to 09H today)"),
     * )
     */
    public function lastNight()
    {
        $timestampLast = Carbon::yesterday()->addHours(21);
        $timestamp = Carbon::today()->addHours(9);

        $response = array(
            'success' => true,
            'data' => $this->getForStatsTimestamp($timestampLast,$timestamp)
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/v1/status",
     *   tags={"legacy"},
     *   summary="Current status in text",
     *   description="",
     *   @OA\Response(response="default", description="Current status in text"),
     * )
     */
    public function status()
    {
        $active =  $incidents = Incident::where('active', true)
            ->where('isFire', true)
            ->whereIn('statusCode', Incident::ACTIVE_STATUS_CODES)
            ->get();

        $date = date("H:i");
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

            $status = "{$date} - {$total} Incêndios em curso combatidos por {$man} meios humanos, {$cars} meios terrestres e {$areal} meios aereos. https://fogos.pt #FogosPT";
        }

        $response = array(
            'success' => true,
            'data' => $status
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/v1/active",
     *   tags={"legacy"},
     *   summary="Active fire list",
     *   description="",
     *   @OA\Response(response="default", description="Active fire list"),
     * )
     */
    public function active()
    {
        $active = Incident::where('active', true)
            ->where('isFire', true)
            ->whereIn('statusCode', Incident::ACTIVE_STATUS_CODES)
            ->get();

        $response = array(
            'success' => true,
            'data' => $active
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/v1/aerial",
     *   tags={"legacy"},
     *   summary="Meios aereos activos em texto",
     *   description="",
     *   @OA\Response(response="default", description="Meios aereos activos em texto"),
     * )
     */
    public function aerial()
    {
        $active =  $incidents = Incident::where('active', true)
            ->where('isFire', true)
            ->whereIn('statusCode', Incident::ACTIVE_STATUS_CODES)
            ->get();

        $date = date("H:i");
        if (empty($active)) {
            $status = "{$date} - Sem registo de incêndios ativos. https://fogos.pt #FogosPT #Status";
        } else {
            $distritos = array();
            foreach ($active as $r) {
                if( !isset($distritos[$r['district']])){

                    $distritos[$r['district']] = array(
                        'm' => $r['aerial'],
                        't' => 1
                    );
                } else {
                    $distritos[$r['district']]['m'] += $r['aerial'];
                    $distritos[$r['district']]['t'] += 1;

                }
            }
            $status = "Distrito - Meios aéreos / incêndios ativos:\r\n";
            foreach($distritos as $d => $k ){
                $status .= "{$d} - {$k['m']}/{$k['t']}\r\n";
            }

        }

        $response = array(
            'success' => true,
            'data' => $status
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/v1/stats",
     *   tags={"legacy"},
     *   summary="Stats district today text",
     *   description="",
     *   @OA\Response(response="default", description="Stats district today text"),
     * )
     */
    public function stats()
    {
        $timestampLast = Carbon::today();
        $timestamp = Carbon::now();

        $result =$this->getForStatsTimestamp($timestampLast,$timestamp);
        $total = $result['total'];
        $distritos = $result['distritos'];

        $status = "Desde as 00:00 de hoje registamos " . $total . " ocorrências de incêndios.\r\n";

        foreach ($distritos as $k => $v) {
            $status .= $k . ' - ' . $v . "\r\n";
        }

        $response = array(
            'success' => true,
            'data' => $status
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/v1/risk",
     *   tags={"legacy"},
     *   summary="RCM for concelho",
     *   description="",
     *   @OA\Parameter(
     *     name="concelho",
     *     required=true,
     *     in="query",
     *     description="Concelho",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(response="default", description="RCM for concelho"),
     * )
     */
    public function risk(Request $request)
    {
        $concelho = $request->get('concelho');
        $concelho = UTF8::ucwords(UTF8::strtolower(trim($concelho)));

        $risk = RCM::where('concelho', $concelho)
                ->orderBy('created', 'desc')
                ->get();

        if(isset($risk[0])){
            $status = $concelho . " risco de incêndio: \r\n Hoje - " . $risk[0]['hoje'] . ",\r\n Amanhã - " . $risk[0]['amanha'] . ",\r\n Depois - " . $risk[0]['depois'] . "\r\n #FogosPT #Risco";
        } else {
            $status = "\"{$concelho}\" não encontrado :'( https://fogos.pt #FogosPT #Risco";
        }

        $response = array(
            'success' => true,
            'data' => $status
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/v1/risk-today",
     *   tags={"legacy"},
     *   summary="RCM for web JS",
     *   description="",
     *   @OA\Response(response="default", description="RCM for web JS"),
     * )
     */
    public function riskToday()
    {
        $risk = RCMForJS::orderBy('created', 'desc')
                        ->limit(1)
                        ->get();

        $risk = $risk[0]->toArray();
        unset($risk['created']);
        unset($risk['updated']);
        unset($risk['_id']);

        $response = array(
            'success' => true,
            'data' => $risk
        );

        return response()->json($response);
    }

    /**
     * @OA\Get(path="/v1/list",
     *   tags={"legacy"},
     *   summary="Active fires for concelho",
     *   description="",
     *   @OA\Parameter(
     *     name="concelho",
     *     required=true,
     *     in="query",
     *     description="Concelho",
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(response="default", description="Active fires for concelho"),
     * )
     */
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
                $status .= $f['location'] . ' - MH: ' . $f['man'] . ' MT: ' . $f['terrain'] . ' MA: ' . $f['aerial'] . ' - ' . $f['status'] . ' - ' . $f['natureza'] . ' https://fogos.pt?fire=' . $f['id'] . ' #FogosPT';
            }
        } else {
            $date = date("H:i");
            $status = "{$date} - Sem registo de incêndios ativos em \"{$concelho}\". https://fogos.pt #FogosPT #Status";
        }

        $response = array(
            'success' => true,
            'data' => $status
        );

        return response()->json($response);
    }

    public function test()
    {
        echo '<pre>';
        $client = TwitterTool::getClient();

        $url = "https://fogos.pt/estatisticas?phantom=1";
        $name = "stats";
        $path = "/var/www/html/public/screenshots/{$name}.png";
        $urlImage = "https://api.fogos.pt/screenshots/{$name}.png";

        $file = file_get_contents($path);
        $data = base64_encode($file);

        $url = "https://upload.twitter.com/1.1/media/upload.json";
        $method = "POST";
        $params = array(
            "media_data" => $data,
        );

        $imageResponse = $client
            ->setPostfields($params)
            ->buildOauth($url, $method)
            ->performRequest();



        var_dump($imageResponse);
        $imageResponse = json_decode($imageResponse);

        print_r($imageResponse);

        // Extract media id
        $id = $imageResponse->media_id_string;

        $fields['media_ids']=$id;

        $url = 'https://api.twitter.com/1.1/statuses/update.json';

        $fields['status'] = 'blabla';

        $response = $client
            ->buildOauth($url, 'POST')
            ->setPostfields($fields)
            ->performRequest();

        $r = json_decode($response);

        $lastId = $r->i;
    }
}

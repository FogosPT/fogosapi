<?php

namespace App\Http\Controllers;

use App\Models\Warning;
use App\Models\WarningAgif;
use App\Models\WeatherStation;
use App\Models\WeatherWarning;
use App\Tools\BlueskyTool;
use App\Tools\FacebookTool;
use App\Tools\NotificationTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;


class WarningsController extends Controller
{
    public function ipma()
    {
        $warnings = WeatherWarning::where('endTime', '>=', Carbon::now())
            ->orderBy('startTime', 'asc')
            ->get();

        $data = $warnings->map(function (WeatherWarning $w) {
            return [
                'text'              => $w->text,
                'awarenessTypeName' => $w->type,
                'idAreaAviso'       => $w->district,
                'startTime'         => Carbon::parse($w->startTime)->format('Y-m-d\TH:i:s'),
                'awarenessLevelID'  => $w->level,
                'endTime'           => Carbon::parse($w->endTime)->format('Y-m-d\TH:i:s'),
            ];
        })->unique(function (array $row) {
            return implode('|', [
                $row['idAreaAviso'],
                $row['awarenessTypeName'],
                $row['awarenessLevelID'],
                $row['startTime'],
                $row['endTime'],
                $row['text'],
            ]);
        })->values();

        return new JsonResponse($data);
    }

    public function add(Request $request)
    {
        $key = $request->header('key');

        if(env('API_WRITE_KEY') !== $key){
            abort(401);
        }

        $status = $request->get('status');

        $warning = new Warning();
        $warning->text = $status;
        $warning->save();

        NotificationTool::sendWarningNotification($status);

        $text = "ALERTA: \r\n" . $status;
        TwitterTool::tweet($text);
        TelegramTool::publish($text);

        $message = "ALERTA: %0A" . $status;
        FacebookTool::publish($message);
    }

    public function addAgif(Request $request)
    {
        $key = $request->header('key');

        if(env('API_WRITE_KEY') !== $key){
            abort(401);
        }

        $status = $request->get('status');

        $warning = new WarningAgif();
        $warning->text = $status;
        $warning->save();

        NotificationTool::sendAllNotification($status);

        TwitterTool::tweet($status);
        TelegramTool::publish($status);
        FacebookTool::publish($status);
        BlueskyTool::publish($status);
    }
}

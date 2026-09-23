<?php

namespace App\Http\Controllers;

use App\Models\Warning;
use App\Models\WarningAgif;
use App\Models\WeatherStation;
use App\Models\WeatherWarning;
use App\Support\WeatherWarningCollapse;
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
        $warnings = WeatherWarning::where('endTime', '>=', Carbon::now())->get();

        return new JsonResponse(WeatherWarningCollapse::collapse($warnings));
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

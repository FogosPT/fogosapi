<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesWarningHistory;
use App\Models\Warning;
use App\Models\WarningAgif;
use App\Models\WeatherStation;
use App\Models\WeatherWarning;
use App\Models\WeatherWarningEvent;
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
    use ServesWarningHistory;

    public function ipma()
    {
        $warnings = WeatherWarning::whereNull('supersededAt')->where('endTime', '>=', Carbon::now())->get();

        return new JsonResponse(WeatherWarningCollapse::collapse($warnings));
    }

    /** Event log of the IPMA warnings; see ServesWarningHistory. */
    public function history(Request $request): JsonResponse
    {
        return $this->warningHistory($request, WeatherWarningEvent::class);
    }

    public function add(Request $request)
    {
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

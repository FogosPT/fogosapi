<?php

namespace App\Http\Controllers;

use App\Models\Warning;
use App\Tools\FacebookTool;
use App\Tools\NotificationTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class WarningsController extends Controller
{
    public function add(Request $request)
    {
        $key = $request->header('key');

        if (env('API_WRITE_KEY') !== $key) {
            abort(401);
        }

        $status = $request->get('status');

        $warning = new Warning();
        $warning->text = $status;
        $warning->save();

        NotificationTool::sendWarningNotification($status);

        $text = "ALERTA: \r\n".$status;
        TwitterTool::tweet($text);
        TelegramTool::publish($text);

        $message = 'ALERTA: %0A'.$status;
        FacebookTool::publish($message);
    }
}

<?php


namespace App\Tools;


class TelegramTool
{
    public static function publish($status)
    {
        if(!env('TELEGRAM_ENABLE')){
            return;
        }

        $apiToken = env('TELEGRAM_API_TOKEN');

        $data = [
            'chat_id' => '@fogospt',
            'text' => $status
        ];

        file_get_contents("https://api.telegram.org/bot{$apiToken}/sendMessage?" . http_build_query($data));
    }

    public static function publishImage($status, $imagePath)
    {
        if(!env('TELEGRAM_ENABLE')){
            return;
        }

        $apiToken = env('TELEGRAM_API_TOKEN');

        $cmd = "curl -s -X POST \"https://api.telegram.org/{$apiToken}/sendPhoto?chat_id=@fogospt\" -F photo=\"@{$imagePath}\" -F caption=\"{$status}\" > /dev/null &";

        exec($cmd);
    }
}

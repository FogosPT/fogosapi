<?php

namespace App\Tools;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class TelegramTool
{
    /**
     * Generic Telegram sendMessage via POST.
     * All Telegram message sends should use this method.
     *
     * @param string $apiToken  Bot API token
     * @param array  $data      Payload (chat_id, text, message_thread_id, etc.)
     */
    public static function sendMessage(string $apiToken, array $data): void
    {
        try {
            $client = new Client();
            $client->post("https://api.telegram.org/bot{$apiToken}/sendMessage", [
                'json' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage failed: ' . $e->getMessage());
        }
    }

    public static function publish($status)
    {
        if (!env('TELEGRAM_ENABLE')) {
            return;
        }

        $apiToken = env('TELEGRAM_API_TOKEN');

        self::sendMessage($apiToken, [
            'chat_id' => '@fogospt',
            'text' => $status,
        ]);
    }

    public static function publishImage($status, $imagePath)
    {
        if (!env('TELEGRAM_ENABLE')) {
            return;
        }

        $apiToken = env('TELEGRAM_API_TOKEN');

        try {
            $client = new Client();
            $client->post("https://api.telegram.org/bot{$apiToken}/sendPhoto", [
                'multipart' => [
                    ['name' => 'chat_id', 'contents' => '@fogospt'],
                    ['name' => 'caption', 'contents' => $status],
                    ['name' => 'photo', 'contents' => fopen($imagePath, 'r'), 'filename' => basename($imagePath)],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram publishImage failed: ' . $e->getMessage());
        }
    }
}

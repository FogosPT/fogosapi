<?php

namespace App\Tools;

use Illuminate\Support\Facades\Log;

class FacebookTool
{
    private static function getUrl($message)
    {
        $pageId = env('FACEBOOK_PAGE_ID');
        $clientId = env('FACEBOOK_CLIENT_ID');
        $clientSecret = env('FACEBOOK_CLIENT_SECRET');
        $accessCode = env('FACEBOOK_ACCESS_CODE');

        return "https://graph.facebook.com/{$pageId}/feed?client_id={$clientId}&client_secret={$clientSecret}&access_token={$accessCode}&message={$message}";
    }

    private static function getEmergenciasUrl($message)
    {
        $pageId = env('FACEBOOK_PAGE_ID_EMERGENCIAS');
        $clientId = env('FACEBOOK_CLIENT_ID_EMERGENCIAS');
        $clientSecret = env('FACEBOOK_CLIENT_SECRET_EMERGENCIAS');
        $accessCode = env('FACEBOOK_ACCESS_CODE_EMERGENCIAS');

        return "https://graph.facebook.com/{$pageId}/feed?client_id={$clientId}&client_secret={$clientSecret}&access_token={$accessCode}&message={$message}";
    }

    public static function publish($status)
    {
        if (!env('FACEBOOK_ENABLE')) {
            return;
        }
        try{
            $client = new \GuzzleHttp\Client();
            $client->request('POST', self::getUrl($status));
        } catch (\Exception $e){
            Log::error($e->getMessage());
        }
    }

    public static function publishWithImage($status, $imagePath)
    {
        if (!env('FACEBOOK_ENABLE')) {
            return;
        }

        if (!$imagePath || !file_exists($imagePath)) {
            Log::error('FacebookTool::publishWithImage missing file: ' . $imagePath);
            return;
        }

        $pageId = env('FACEBOOK_PAGE_ID');
        $accessCode = env('FACEBOOK_ACCESS_CODE');

        try {
            $client = new \GuzzleHttp\Client();
            $client->request('POST', "https://graph.facebook.com/{$pageId}/photos", [
                'multipart' => [
                    ['name' => 'access_token', 'contents' => $accessCode],
                    ['name' => 'caption', 'contents' => $status],
                    ['name' => 'source', 'contents' => fopen($imagePath, 'r'), 'filename' => basename($imagePath)],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('FacebookTool::publishWithImage failed: ' . $e->getMessage());
        }
    }

    public static function publishEmergencias($status)
    {
        if (!env('FACEBOOK_ENABLE')) {
            return;
        }

        try{
            $client = new \GuzzleHttp\Client();
            //$response = $client->request('POST', self::getEmergenciasUrl($status));

        } catch (\Exception $e){
            Log::error($e->getMessage());
        }

    }
}

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
            return null;
        }
        try{
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', self::getUrl($status));
            $body = json_decode((string) $response->getBody(), true);
            return $body['id'] ?? null;
        } catch (\Exception $e){
            Log::error($e->getMessage());
            return null;
        }
    }

    public static function publishWithImage($status, $imagePath)
    {
        if (!env('FACEBOOK_ENABLE')) {
            return null;
        }

        if (!$imagePath || !file_exists($imagePath)) {
            Log::error('FacebookTool::publishWithImage missing file: ' . $imagePath);
            return null;
        }

        $pageId = env('FACEBOOK_PAGE_ID');
        $accessCode = env('FACEBOOK_ACCESS_CODE');

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', "https://graph.facebook.com/{$pageId}/photos", [
                'multipart' => [
                    ['name' => 'access_token', 'contents' => $accessCode],
                    ['name' => 'caption', 'contents' => $status],
                    ['name' => 'source', 'contents' => fopen($imagePath, 'r'), 'filename' => basename($imagePath)],
                ],
            ]);
            $body = json_decode((string) $response->getBody(), true);
            return $body['post_id'] ?? null;
        } catch (\Exception $e) {
            Log::error('FacebookTool::publishWithImage failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function commentOnPost($postId, $message)
    {
        if (!env('FACEBOOK_ENABLE') || !$postId) {
            return null;
        }

        $accessCode = env('FACEBOOK_ACCESS_CODE');

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', "https://graph.facebook.com/{$postId}/comments", [
                'form_params' => [
                    'access_token' => $accessCode,
                    'message' => $message,
                ],
            ]);
            $body = json_decode((string) $response->getBody(), true);
            return $body['id'] ?? null;
        } catch (\Exception $e) {
            Log::error('FacebookTool::commentOnPost failed: ' . $e->getMessage());
            return null;
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

<?php

namespace App\Tools;

use Illuminate\Support\Facades\Log;

class FacebookTool
{
    private const APP_PROMO_COMMENT = 'Se tirares fotos de incêndios, pedimos que as captes e submetas através da Aplicação fogos.pt disponível para Android e iOS. Dessa forma, as fotos ficam com localização e hora, podendo mais facilmente ser usadas em termos operacionais pelas entidades oficiais.';

    public static function publish($status)
    {
        if (!env('FACEBOOK_ENABLE')) {
            return null;
        }
        try{
            $client = new \GuzzleHttp\Client();
            // Token and message go in the body: in the URL they leak into
            // exception messages/logs, and the message was not encoded.
            $response = $client->request('POST', 'https://graph.facebook.com/'.env('FACEBOOK_PAGE_ID').'/feed', [
                'form_params' => [
                    'access_token' => env('FACEBOOK_ACCESS_CODE'),
                    // Callers still build messages for the old URL form with
                    // %0A as line breaks; in the body that would be literal.
                    'message' => str_ireplace('%0A', "\n", (string) $status),
                ],
            ]);
            $body = json_decode((string) $response->getBody(), true);
            $postId = $body['id'] ?? null;
            self::commentOnPost($postId, self::APP_PROMO_COMMENT);
            return $postId;
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
            $postId = $body['post_id'] ?? null;
            self::commentOnPost($postId, self::APP_PROMO_COMMENT);
            return $postId;
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

    }
}

<?php

namespace App\Tools;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use phpDocumentor\Reflection\Types\Self_;

class BlueskyTool
{
    private static function getToken()
    {
        $client = new \GuzzleHttp\Client();

        $data = [
            'identifier' => 'fogospt.bsky.social',
            'password' => env('BSKY_APP_PASSWORD')
        ];

        $response = $client->request('POST', 'https://bsky.social/xrpc/com.atproto.server.createSession', ['json' => $data]);

        $result = json_decode($response->getBody(),true);

        return $result;
    }
    public static function publish($status)
    {
        $session = self::getToken();

        var_dump($session);

        $data = [
            'repo' => $session['did'],
            'collection' => 'app.bsky.feed.post',
            'record' => [
                '$type' => 'app.bsky.feed.post',
                'text' => $status,
                'createdAt' => Carbon::now()
            ]
        ];

        $headers = [
            'json' => $data,
            'headers' => [
                'Authorization' => "Bearer " . $session['accessJwt']
            ]
        ];

        try{
            $client = new \GuzzleHttp\Client();
            $client->request('POST', 'https://bsky.social/xrpc/com.atproto.repo.createRecord', $headers);
        } catch (\Exception $e){
            Log::error($e->getMessage());
        }
    }
}

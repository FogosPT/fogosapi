<?php


namespace App\Tools;

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

    private static function getImageUrl($message, $imageUrl)
    {
        $clientId = env('FACEBOOK_CLIENT_ID');
        $clientSecret = env('FACEBOOK_CLIENT_SECRET');
        $accessCode = env('FACEBOOK_ACCESS_CODE');

        $imageUrl = urlencode($imageUrl);

        return "https://graph.facebook.com/me/photos?client_id={$clientId}&client_secret={$clientSecret}&access_token={$accessCode}&url={$imageUrl}&caption={$message}";
    }

    public static function publish($status)
    {
        if(!env('FACEBOOK_ENABLE')){
            return;
        }

        $client = new \GuzzleHttp\Client();
        $client->request('POST', self::getUrl($status));
    }

    public static function publishWithImage($status, $imageUrl)
    {
        if(!env('FACEBOOK_ENABLE')){
            return;
        }

        $client = new \GuzzleHttp\Client();
        $client->request('POST', self::getImageUrl($status, $imageUrl));
    }
}

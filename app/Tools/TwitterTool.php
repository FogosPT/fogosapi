<?php

namespace App\Tools;

use Illuminate\Support\Facades\Log;
use Noweh\TwitterApi\Client;

class TwitterTool
{
    private static $client = false;
    private static $clientVOST = false;
    private static $clientEmergencias = false;

    public static function getClient()
    {
        if (!self::$client) {
            $settings = [
                'access_token' => env('TWITTER_OAUTH_ACCESS_TOKEN'),
                'access_token_secret' => env('TWITTER_OAUTH_ACCESS_TOKEN_SECRET'),
                'consumer_key' => env('TWITTER_CONSUMER_KEY'),
                'consumer_secret' => env('TWITTER_CONSUMER_SECRET'),
                'bearer_token' => env('TWITTER_BEARER_TOKEN'),
                'account_id' => 'fogospt',
            ];

            self::$client = new Client($settings);
        }

        return self::$client;
    }

    // The VOST and Emergências accounts used \TwitterAPIExchange (API v1.1),
    // which is not installed, so every call crashed. They now use the same v2
    // client with their own OAuth 1.0a credentials.
    public static function getVOSTClient()
    {
        if (!self::$clientVOST) {
            self::$clientVOST = new Client([
                'access_token' => env('TWITTER_OAUTH_ACCESS_TOKEN_VOST'),
                'access_token_secret' => env('TWITTER_OAUTH_ACCESS_TOKEN_SECRET_VOST'),
                'consumer_key' => env('TWITTER_CONSUMER_KEY_VOST'),
                'consumer_secret' => env('TWITTER_CONSUMER_SECRET_VOST'),
                'bearer_token' => (string) env('TWITTER_BEARER_TOKEN_VOST', ''),
                'account_id' => (string) env('TWITTER_ACCOUNT_ID_VOST', '0'),
            ]);
        }

        return self::$clientVOST;
    }

    public static function getClientEmergencias()
    {
        if (!self::$clientEmergencias) {
            self::$clientEmergencias = new Client([
                'access_token' => env('TWITTER_OAUTH_ACCESS_TOKEN_EMERGENCIAS'),
                'access_token_secret' => env('TWITTER_OAUTH_ACCESS_TOKEN_SECRET_EMERGENCIAS'),
                'consumer_key' => env('TWITTER_CONSUMER_KEY_EMERGENCIAS'),
                'consumer_secret' => env('TWITTER_CONSUMER_SECRET_EMERGENCIAS'),
                'bearer_token' => (string) env('TWITTER_BEARER_TOKEN_EMERGENCIAS', ''),
                'account_id' => (string) env('TWITTER_ACCOUNT_ID_EMERGENCIAS', '0'),
            ]);
        }

        return self::$clientEmergencias;
    }

    private static function splitTweets($long_string, $max_length = 280, $max_sentences = 10, $encoding = 'UTF-8')
    {
        $string_length = mb_strlen($long_string, $encoding);
        if ($string_length <= $max_length) {
            return [$long_string];
        }

        $words_array = explode(' ', $long_string);
        if (count($words_array) < 2) {
            return $words_array;
        }

        $first_word = $words_array[0];
        if (mb_strlen($first_word, $encoding) > $max_length) {
            return [mb_substr($first_word, 0, $max_length, $encoding)];
        }

        $sentences_array = [];
        $ended_word = 0;

        for ($sentence = 0; $sentence < $max_sentences; ++$sentence) {
            $short_string = '';

            foreach ($words_array as $word_number => $current_word) {
                $expected_length = mb_strlen($short_string.' '.$current_word, $encoding);
                if ($expected_length > $max_length) {
                    break;
                }

                $short_string .= $current_word.' ';
                $ended_word = $word_number + 1;
            }

            $sentences_array[] = $short_string;
            $words_array = array_slice($words_array, $ended_word);

            if (!$words_array) {
                break;
            }
        }

        return $sentences_array;
    }

    /**
     * Returns the id of the last tweet posted, or null/false when disabled or
     * on failure. Never throws: callers post to several channels and record
     * "already sent" state after tweeting, so a Twitter error (429, duplicate
     * text, ...) used to abort the job and, on retry, repeat the pushes and
     * posts that ran before it.
     */
    public static function tweet($text, $lastId = false, $imagePath = false, $emergencias = false, $vost = false, $bypassEnable = false)
    {
        if (!env('TWITTER_ENABLE')) {
            return false;
        }

        try {
            return self::postTweet($text, $lastId, $imagePath, $emergencias, $vost);
        } catch (\Throwable $e) {
            Log::warning('Twitter post failed: '.$e->getMessage(), ['vost' => $vost, 'emergencias' => $emergencias]);

            return null;
        }
    }

    private static function postTweet($text, $lastId, $imagePath, $emergencias, $vost)
    {
        if($vost){
            $client = self::getVOSTClient();
        } else if($emergencias){
            $client = self::getClientEmergencias();
        } else {
            $client = self::getClient();
        }

        $fields = [];

        if ($imagePath && file_exists($imagePath)) {
            $file_data = base64_encode(file_get_contents($imagePath));
            $media_info = $client->uploadMedia()->upload($file_data);

            // Extract media id
            $id = $media_info["media_id"];

            $fields['media']['media_ids'] = [(string)$id];
        }

        $tweets = self::splitTweets($text);

        foreach ($tweets as $t) {
            $fields['text'] = $t;

            if ($lastId) {
                $fields['reply']['in_reply_to_tweet_id'] = $lastId;
            }

            $response = $client->tweet()->create()->performRequest(
                $fields
            );

            if(isset($response->data->id)){
                $lastId = $response->data->id;
            } else {
                $lastId = null;
            }

            if(isset($fields['media']['media_ids'])){
                unset($fields['media']['media_ids']);
                unset($fields['media']);
            }
        }
        return $lastId;
    }

    public static function retweetVost($id)
    {
        if (!env('TWITTER_ENABLE')) {
            return;
        }

        if (!$id) {
            return;
        }

        // v2 retweets need the VOST numeric user id.
        if (!env('TWITTER_ACCOUNT_ID_VOST')) {
            Log::info('VOST retweet skipped: TWITTER_ACCOUNT_ID_VOST is not set');

            return;
        }

        try {
            self::getVOSTClient()->retweet()->performRequest(['tweet_id' => (string) $id]);
        } catch (\Throwable $e) {
            Log::warning('VOST retweet failed: '.$e->getMessage());
        }
    }
}

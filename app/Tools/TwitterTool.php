<?php

namespace App\Tools;

use Illuminate\Support\Facades\Log;
use Noweh\TwitterApi\Client;

class TwitterTool
{
    private static $client = false;
    private static $clientVOST = false;

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

    public static function getVOSTClient()
    {
        if (!self::$clientVOST) {
            $settings = [
                'oauth_access_token' => env('TWITTER_OAUTH_ACCESS_TOKEN_VOST'),
                'oauth_access_token_secret' => env('TWITTER_OAUTH_ACCESS_TOKEN_SECRET_VOST'),
                'consumer_key' => env('TWITTER_CONSUMER_KEY_VOST'),
                'consumer_secret' => env('TWITTER_CONSUMER_SECRET_VOST'),
            ];

            self::$clientVOST = new \TwitterAPIExchange($settings);
        }

        return self::$clientVOST;
    }

    public static function getClientEmergencias()
    {
        if (!self::$client) {
            $settings = [
                'oauth_access_token' => env('TWITTER_OAUTH_ACCESS_TOKEN_EMERGENCIAS'),
                'oauth_access_token_secret' => env('TWITTER_OAUTH_ACCESS_TOKEN_SECRET_EMERGENCIAS'),
                'consumer_key' => env('TWITTER_CONSUMER_KEY_EMERGENCIAS'),
                'consumer_secret' => env('TWITTER_CONSUMER_SECRET_EMERGENCIAS'),
            ];

            self::$client = new \TwitterAPIExchange($settings);
        }

        return self::$client;
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

    public static function tweet($text, $lastId = false, $imagePath = false, $emergencias = false, $vost = false)
    {
        if (!env('TWITTER_ENABLE')) {
            return false;
        }

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
        $client = self::getVOSTClient();

        $url = 'https://api.twitter.com/1.1/statuses/retweet/' . $id . '.json';

        $response = $client
            ->buildOauth($url, 'POST')
            ->setPostfields([])
            ->performRequest();
    }
}

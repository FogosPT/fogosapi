<?php

namespace App\Jobs;

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class SendFcmNotification extends Job
{
    private const ENDPOINT = '/v1/projects/admob-app-id-6663345165/messages:send';

    public array $message;

    public function __construct(array $message)
    {
        $this->message = $message;
    }

    public function handle(): void
    {
        if (!env('NOTIFICATIONS_ENABLE')) {
            return;
        }

        $client = new Client([
            'base_uri' => 'https://fcm.googleapis.com',
        ]);

        $headers = [
            'allow_redirects' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getAuthToken(),
            ],
            'json' => [
                'message' => $this->message,
            ],
        ];

        Log::debug('FCM dispatch: ' . json_encode($headers));

        try {
            $client->post(self::ENDPOINT, $headers);
        } catch (RequestException $e) {
            Log::error('FCM send failed: ' . $e->getMessage());
            if ($e->getResponse()) {
                Log::error($e->getResponse()->getBody()->getContents());
            }
        }
    }

    private function getAuthToken(): string
    {
        putenv('GOOGLE_APPLICATION_CREDENTIALS=/var/www/html/credentials.json');
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $credentials = ApplicationDefaultCredentials::getCredentials($scopes);
        $auth = $credentials->fetchAuthToken();
        return $auth['access_token'];
    }
}

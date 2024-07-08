<?php

namespace App\Tools;

use App\Models\Incident;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;


class NotificationTool
{
    private static $endpoint = '/v1/projects/admob-app-id-6663345165/messages:send';

    private static function getAuth()
    {
        // specify the path to your application credentials
        putenv('GOOGLE_APPLICATION_CREDENTIALS=/var/www/html/credentials.json');

        // define the scopes for your API call
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        // create middleware
        $middleware = ApplicationDefaultCredentials::getCredentials($scopes);

        $auth = $middleware->fetchAuthToken();

        return $auth;
    }

    public static function test()
    {
        $client = new Client([
            'base_uri' => 'https://fcm.googleapis.com',
        ]);

        $status = 'Test';

        $headers = [
            'allow_redirects' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . self::getClient()['access_token']
            ],
            'json' => [
                'message' => [
                    //'condition' => "'mobile-android-warnings' in topics || 'mobile-ios-warnings' in topics || 'web-warnings' in topics",
                    'topic' => "all",
                    'notification' => [
                        'title' => "Fogos.pt - Aviso",
                        'body' => $status,
                    ],
                    'android' => [
                        'priority' => 'high'
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => "5"
                        ]
                    ]
                ],
            ],
        ];

        Log::debug(json_encode($headers));

        try{
            $response = $client->post(self::$endpoint,$headers );
        } catch (RequestException $e){
            var_dump($e->getMessage());
            var_dump($e->getResponse()->getBody()->getContents());
        }
    }


    private static function sendRequest($topic, $status, $location, $id)
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
                'Authorization' => 'Bearer ' . self::getAuth()['access_token']
            ],
            'json' => [
                'message' => [
                    //'condition' => "'mobile-android-warnings' in topics || 'mobile-ios-warnings' in topics || 'web-warnings' in topics",
                    'condition' => $topic,
                    'notification' => [
                        'title' => "Fogos.pt - {$location}",
                        'body' => $status,
                    ],
                    'android' => [
                        'priority' => 'high'
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => "5"
                        ]
                    ],
                    'data' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'fireId' => $id
                    ],
                ],
            ],
        ];

        Log::debug(json_encode($headers));
        try{
            $response = $client->post(self::$endpoint,$headers );
        } catch (RequestException $e){
            Log::debug($e->getMessage());
            Log::debug($e->getResponse()->getBody()->getContents());
        }

    }

    private static function sendCustomTitleRequest($topic, $status, $title, $forceEnable = false)
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
                'Authorization' => 'Bearer ' . self::getAuth()['access_token']
            ],
            'json' => [
                'message' => [
                    //'condition' => "'mobile-android-warnings' in topics || 'mobile-ios-warnings' in topics || 'web-warnings' in topics",
                    'condition' => $topic,
                    'notification' => [
                        'title' => "Fogos.pt - {$title}",
                        'body' => $status,
                    ],
                    'android' => [
                        'priority' => 'high'
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => "5"
                        ]
                    ],
                ],
            ],
        ];

        Log::debug(json_encode($headers));

        try{
            $response = $client->post(self::$endpoint,$headers );
        } catch (RequestException $e){
            Log::debug($e->getMessage());
            Log::debug($e->getResponse()->getBody()->getContents());
        }

    }

    private static function buildLegacyTopic($id)
    {
        if (env('APP_ENV') === 'production') {
            $topic = "'web-{$id}' in topics || 'mobile-android-{$id}' in topics || 'mobile-ios-{$id}' in topics";
        } else {
            $topic = "'dev-web-{$id}' in topics || 'dev-mobile-android-{$id}' in topics || 'dev-mobile-ios-{$id}' in topics";
        }

        return $topic;
    }

    private static function buildLegacyImportantTopic()
    {
        if (env('APP_ENV') === 'production') {
            $topic = "'web-important' in topics || 'mobile-android-important' in topics || 'mobile-ios-important' in topics";
        } else {
            $topic = "'dev-web-important' in topics || 'dev-mobile-android-important' in topics || 'dev-mobile-ios-important' in topics";
        }

        return $topic;
    }

    private static function buildTopic($id, $important = false)
    {
        if (env('APP_ENV') === 'production') {
            $topic = "'incident-{$id}' in topics";
        } else {
            $topic = "'dev-incident-{$id}' in topics";
        }

        if ($important) {
            if (env('APP_ENV') === 'production') {
                $topic .= " || 'incident-important' in topics";
            } else {
                $topic .= " || 'dev-incident-important' in topics";
            }
        }

        return $topic;
    }

    public static function send($status, $location, $id, $topic = false)
    {
        if (!$topic) {
            $topic = self::buildTopic($id, true);
        }

        self::sendRequest($topic, $status, $location, $id);

        if (env('LEGACY_ENABLE')) {
            $topic = self::buildLegacyTopic($id);
            self::sendRequest($topic, $status, $location, $id);
        }
    }

    public static function sendImportant($status, $incidentId)
    {
        $topic = self::buildLegacyImportantTopic();

        $title = 'Ocorrência Importante';


        $client = new Client([
            'base_uri' => 'https://fcm.googleapis.com',
        ]);

        $headers = [
            'allow_redirects' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . self::getAuth()['access_token']
            ],
            'json' => [
                'message' => [
                    //'condition' => "'mobile-android-warnings' in topics || 'mobile-ios-warnings' in topics || 'web-warnings' in topics",
                    'condition' => $topic,
                    'notification' => [
                        'title' => "Fogos.pt - {$title}",
                        'body' => $status,
                    ],
                    'android' => [
                        'priority' => 'high'
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => "5"
                        ]
                    ],
                    'data' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'fireId' => $incidentId
                    ]
                ],
            ],
        ];

        Log::debug(json_encode($headers));

        try{
            $response = $client->post(self::$endpoint,$headers );
        } catch (RequestException $e){
            Log::debug($e->getMessage());
            Log::debug($e->getResponse()->getBody()->getContents());
        }

    }

    public static function sendWarning($status, $topic)
    {
        $title = 'Alerta';

        $headers = [
            'allow_redirects' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'key='.env('FIREBASE_KEY'),
            ],
            'json' => [
                'condition' => $topic,
                'notification' => [
                    'title' => "Fogos.pt - {$title}",
                    'body' => $status,
                    'sound' => 'default',
                    'icon' => 'https://fogos.pt/img/logo.svg',
                ],
            ],
        ];

        Log::debug(json_encode($headers));

        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', 'https://fcm.googleapis.com/fcm/send', $headers);
    }


    public static function sendNewCosNotification(Incident $incident)
    {
        $status = 'Novo Comandante de Operações de socorro: '.$incident->cos;
        self::send($status, $incident->location, $incident->id);
    }

    public static function sendNewPOSITNotification(Incident $incident)
    {
        $status = 'Novo ponto de situação: '.$incident->POSITDescricao;
        self::send($status, $incident->location, $incident->id);
    }

    public static function sendNewStatusNotification(Incident $incident, $incidentStatusHistory)
    {
        $status = "Alteração de estado: de {$incidentStatusHistory['status']} para {$incident->status}";
        self::send($status, $incident->location, $incident->id);
    }

    public static function sendNewFireNotification(Incident $incident)
    {
        $legacyTopic = null;

        switch ($incident->district) {
            case 'Bragança':
                $legacyTopic = 'Braganca';

                break;

            case 'Évora':
                $legacyTopic = 'Evora';

                break;

            case 'Castelo Branco':
                $legacyTopic = 'CasteloBranco';

                break;

            case 'Santarém':
                $legacyTopic = 'Santarem';

                break;

            case 'Setúbal':
                $legacyTopic = 'Setubal';

                break;

            case 'Viana Do Castelo':
                $legacyTopic = 'VianadoCastelo';

                break;

            case 'Vila Real':
                $legacyTopic = 'VilaReal';

                break;

            default:
                $legacyTopic = $incident->district;

                break;
        }

        $newTopic = $incident->dico.'00';

        if (env('APP_ENV') === 'production') {
            $topic = "'{$legacyTopic}' in topics || 'web-{$newTopic}' in topics || 'mobile-android-{$newTopic}' in topics || 'mobile-ios-{$newTopic}' in topics";
        } else {
            $topic = "'{$legacyTopic}' in topics || 'dev-web-{$newTopic}' in topics || 'dev-mobile-android-{$newTopic}' in topics || 'dev-mobile-ios-{$newTopic}' in topics";
        }

        $status = "Novo incêndio em {$incident->location}";

        self::send($status, $incident->location, $incident->id, $topic);
    }

    public static function sendWarningMadeiraNotification($title, $description)
    {
        self::sendCustomTitleRequest('Madeira', $description, $title);
    }

    public static function sendPlaneNotification($status)
    {
        $topic = "'mobile-android-planes' in topics || 'mobile-ios-planes' in topics";
        $title = 'Fogos.pt - Meio Aéreo';
        self::sendCustomTitleRequest($topic, $status, $title,true);
    }

    public static function sendWarningNotification($status)
    {
        $topic = "'mobile-android-warnings' in topics || 'mobile-ios-warnings' in topics || 'web-warnings' in topics";

        self::sendWarning($status, $topic);
    }

    public static function sendAllNotification($status)
    {
        $topic = "all";

        self::sendWarning($status, $topic);
    }
}

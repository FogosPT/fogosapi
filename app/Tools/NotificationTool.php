<?php

namespace App\Tools;

use App\Models\Incident;
use App\Models\IncidentStatusHistory;
use Illuminate\Support\Facades\Log;

class NotificationTool
{
    private static function sendRequest($topic, $status, $location, $id)
    {
        if (!env('NOTIFICATIONS_ENABLE')) {
            return;
        }

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
                    'title' => "Fogos.pt - {$location}",
                    'body' => $status,
                    'sound' => 'default',
                    'click_action' => 'https://fogos.pt/fogo/{$id}',
                    'icon' => 'https://fogos.pt/img/logo.svg',
                ],
            ],
        ];

        $client = new \GuzzleHttp\Client();
        sleep(1);
        $client->request('POST', 'https://fcm.googleapis.com/fcm/send', $headers);
    }

    private static function sendCustomTitleRequest($topic, $status, $title, $forceEnable = false)
    {
        if (!env('NOTIFICATIONS_ENABLE') && !$forceEnable) {
            return;
        }

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
                    'click_action' => 'https://fogos.pt/avisos',
                    'icon' => 'https://fogos.pt/img/logo.svg',
                ],
            ],
        ];

        Log::debug('sendCustomTitleRequest => ' . $topic);
        Log::debug('sendCustomTitleRequest => ' . $status);


        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', 'https://fcm.googleapis.com/fcm/send', $headers);

        Log::debug('sendImportant => ' . $status );
        Log::debug($response->getStatusCode());
        Log::debug($response->getBody());
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

        Log::debug('buildLegacyImportantTopic => ' . $topic);

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

    public static function sendImportant($status)
    {
        $topic = self::buildLegacyImportantTopic();

        $title = 'Ocorrência Importante';

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
                    'click_action' => 'https://fogos.pt/fogo/{$id}',
                    'icon' => 'https://fogos.pt/img/logo.svg',
                ],
            ],
        ];

        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', 'https://fcm.googleapis.com/fcm/send', $headers);

        Log::debug('sendImportant => ' . $status );
        Log::debug($response->getStatusCode());
        Log::debug($response->getBody());
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

    public static function sendNewStatusNotification(Incident $incident, IncidentStatusHistory $incidentStatusHistory)
    {
        $status = "Alteração de estado: de {$incidentStatusHistory->status} para {$incident->status}";
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
}

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
        putenv('GOOGLE_APPLICATION_CREDENTIALS=/var/www/html/credentials.json');
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $middleware = ApplicationDefaultCredentials::getCredentials($scopes);
        return $middleware->fetchAuthToken();
    }

    // ──────────────────────────────────────────────
    //  Topic builders — unified + legacy
    // ──────────────────────────────────────────────

    private static function prefix(): string
    {
        return env('APP_ENV') === 'production' ? '' : 'dev-';
    }

    /**
     * Build a unified topic string for an incident.
     * New clients subscribe to "incident-<id>", legacy clients
     * still use "mobile-android-<id>", "mobile-ios-<id>", "web-<id>".
     */
    private static function buildIncidentTopic(string $id, bool $includeImportant = false): string
    {
        $p = self::prefix();
        $parts = [
            "'{$p}incident-{$id}' in topics",
        ];

        if (env('LEGACY_ENABLE')) {
            $parts[] = "'{$p}web-{$id}' in topics";
            $parts[] = "'{$p}mobile-android-{$id}' in topics";
            $parts[] = "'{$p}mobile-ios-{$id}' in topics";
        }

        if ($includeImportant) {
            $parts[] = "'{$p}incident-important' in topics";
            if (env('LEGACY_ENABLE')) {
                $parts[] = "'{$p}web-important' in topics";
                $parts[] = "'{$p}mobile-android-important' in topics";
                $parts[] = "'{$p}mobile-ios-important' in topics";
            }
        }

        return self::combineConditions($parts);
    }

    /**
     * Build topic for "important" notifications only.
     */
    private static function buildImportantTopic(): string
    {
        $p = self::prefix();
        $parts = [
            "'{$p}incident-important' in topics",
        ];

        if (env('LEGACY_ENABLE')) {
            $parts[] = "'{$p}web-important' in topics";
            $parts[] = "'{$p}mobile-android-important' in topics";
            $parts[] = "'{$p}mobile-ios-important' in topics";
        }

        return self::combineConditions($parts);
    }

    /**
     * Build topic for warnings.
     */
    private static function buildWarningsTopic(): string
    {
        $p = self::prefix();
        $parts = [
            "'{$p}warnings' in topics",
        ];

        if (env('LEGACY_ENABLE')) {
            $parts[] = "'{$p}mobile-android-warnings' in topics";
            $parts[] = "'{$p}mobile-ios-warnings' in topics";
            $parts[] = "'{$p}web-warnings' in topics";
        }

        return self::combineConditions($parts);
    }

    /**
     * Build topic for planes.
     */
    private static function buildPlanesTopic(): string
    {
        $p = self::prefix();
        $parts = [
            "'{$p}planes' in topics",
        ];

        if (env('LEGACY_ENABLE')) {
            $parts[] = "'{$p}mobile-android-planes' in topics";
            $parts[] = "'{$p}mobile-ios-planes' in topics";
        }

        return self::combineConditions($parts);
    }

    /**
     * Build topic for new fire in a district (by dico code).
     */
    private static function buildNewFireTopic(Incident $incident): string
    {
        $p = self::prefix();
        $newTopic = $incident->dico . '00';

        $parts = [
            "'{$p}district-{$newTopic}' in topics",
        ];

        if (env('LEGACY_ENABLE')) {
            $legacyDistrict = self::getLegacyDistrictTopic($incident->district);
            $parts[] = "'{$legacyDistrict}' in topics";
            $parts[] = "'{$p}web-{$newTopic}' in topics";
            $parts[] = "'{$p}mobile-android-{$newTopic}' in topics";
            $parts[] = "'{$p}mobile-ios-{$newTopic}' in topics";
        }

        return self::combineConditions($parts);
    }

    /**
     * Build topic for ALL new incidents in a district (by dico code).
     * Subscribers receive notifications for every incident type, not just fires.
     */
    private static function buildAllIncidentsTopic(Incident $incident): string
    {
        $p = self::prefix();
        $newTopic = $incident->dico . '00';

        return "'{$p}district-all-{$newTopic}' in topics";
    }

    /**
     * Public helper: build incident topic WITHOUT "important".
     * Use when the notification should only reach subscribers of a specific incident.
     */
    public static function buildIncidentTopicOnly(string $id): string
    {
        return self::buildIncidentTopic($id, false);
    }

    /**
     * FCM conditions support max 5 topics with || / &&.
     * If we exceed 5, we need to split into multiple sends.
     * For now, combine up to 5 with ||.
     */
    private static function combineConditions(array $parts): string
    {
        // FCM limit: max 5 topics per condition
        $parts = array_slice($parts, 0, 5);
        return implode(' || ', $parts);
    }

    private static function getLegacyDistrictTopic(string $district): string
    {
        $map = [
            'Bragança' => 'Braganca',
            'Évora' => 'Evora',
            'Castelo Branco' => 'CasteloBranco',
            'Santarém' => 'Santarem',
            'Setúbal' => 'Setubal',
            'Viana Do Castelo' => 'VianadoCastelo',
            'Vila Real' => 'VilaReal',
        ];

        return $map[$district] ?? $district;
    }

    // ──────────────────────────────────────────────
    //  Core send methods
    // ──────────────────────────────────────────────

    /**
     * Send a notification to a topic condition.
     *
     * @param string $condition  FCM condition string
     * @param string $title      Notification title (prepended with "Fogos.pt - ")
     * @param string $body       Notification body text
     * @param array  $data       Optional data payload
     */
    private static function sendToCondition(string $condition, string $title, string $body, array $data = []): void
    {
        if (!env('NOTIFICATIONS_ENABLE')) {
            return;
        }

        $client = new Client([
            'base_uri' => 'https://fcm.googleapis.com',
        ]);

        $message = [
            'condition' => $condition,
            'notification' => [
                'title' => "Fogos.pt - {$title}",
                'body' => $body,
            ],
            'android' => [
                'priority' => 'high',
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '5',
                ],
            ],
        ];

        if (!empty($data)) {
            $message['data'] = $data;
        }

        $headers = [
            'allow_redirects' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . self::getAuth()['access_token'],
            ],
            'json' => [
                'message' => $message,
            ],
        ];

        Log::debug(json_encode($headers));

        try {
            $client->post(self::$endpoint, $headers);
        } catch (RequestException $e) {
            Log::error('FCM send failed: ' . $e->getMessage());
            if ($e->getResponse()) {
                Log::error($e->getResponse()->getBody()->getContents());
            }
        }
    }

    /**
     * Send to a plain topic name (not condition).
     */
    private static function sendToTopic(string $topic, string $title, string $body, array $data = []): void
    {
        if (!env('NOTIFICATIONS_ENABLE')) {
            return;
        }

        $client = new Client([
            'base_uri' => 'https://fcm.googleapis.com',
        ]);

        $message = [
            'topic' => $topic,
            'notification' => [
                'title' => "Fogos.pt - {$title}",
                'body' => $body,
            ],
            'android' => [
                'priority' => 'high',
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '5',
                ],
            ],
        ];

        if (!empty($data)) {
            $message['data'] = $data;
        }

        $headers = [
            'allow_redirects' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . self::getAuth()['access_token'],
            ],
            'json' => [
                'message' => $message,
            ],
        ];

        Log::debug(json_encode($headers));

        try {
            $client->post(self::$endpoint, $headers);
        } catch (RequestException $e) {
            Log::error('FCM send failed: ' . $e->getMessage());
            if ($e->getResponse()) {
                Log::error($e->getResponse()->getBody()->getContents());
            }
        }
    }

    private static function fireData(string $incidentId): array
    {
        return [
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'fireId' => $incidentId,
        ];
    }

    /**
     * Send a data-only message to a topic (no "notification" block).
     * This ensures the message is always handled by the app's background handler,
     * even when the app is in background/terminated (critical for Android).
     */
    private static function sendDataOnlyToTopic(string $topic, array $data): void
    {
        if (!env('NOTIFICATIONS_ENABLE')) {
            return;
        }

        $client = new Client([
            'base_uri' => 'https://fcm.googleapis.com',
        ]);

        $message = [
            'topic' => $topic,
            'data' => $data,
            'android' => [
                'priority' => 'high',
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                    'apns-push-type' => 'background',
                ],
                'payload' => [
                    'aps' => [
                        'content-available' => 1,
                    ],
                ],
            ],
        ];

        $headers = [
            'allow_redirects' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . self::getAuth()['access_token'],
            ],
            'json' => [
                'message' => $message,
            ],
        ];

        Log::debug('FCM data-only: ' . json_encode($headers));

        try {
            $client->post(self::$endpoint, $headers);
        } catch (RequestException $e) {
            Log::error('FCM data-only send failed: ' . $e->getMessage());
            if ($e->getResponse()) {
                Log::error($e->getResponse()->getBody()->getContents());
            }
        }
    }

    // ──────────────────────────────────────────────
    //  Public API — same method signatures as before
    // ──────────────────────────────────────────────

    public static function test()
    {
        self::sendToTopic('all', 'Aviso', 'Test');
    }

    public static function send($status, $location, $id, $topic = false)
    {
        if (!$topic) {
            $topic = self::buildIncidentTopic($id, true);
        }

        self::sendToCondition($topic, $location, $status, self::fireData($id));
    }

    public static function sendImportant($status, $incidentId)
    {
        $topic = self::buildImportantTopic();
        self::sendToCondition($topic, 'Ocorrência Importante', $status, self::fireData($incidentId));
    }

    public static function sendWarning($status, $topic = null)
    {
        if (!$topic) {
            $topic = self::buildWarningsTopic();
        }
        self::sendToCondition($topic, 'Alerta', $status);
    }

    public static function sendNewCosNotification(Incident $incident)
    {
        $status = 'Novo Comandante de Operações de socorro: ' . $incident->cos;
        $topic = self::buildIncidentTopic($incident->id, false);
        self::send($status, $incident->location, $incident->id, $topic);
    }

    public static function sendNewPOSITNotification(Incident $incident)
    {
        $status = 'Novo ponto de situação: ' . $incident->POSITDescricao;
        $topic = self::buildIncidentTopic($incident->id, false);
        self::send($status, $incident->location, $incident->id, $topic);
    }

    public static function sendNewStatusNotification(Incident $incident, $incidentStatusHistory)
    {
        $status = "Alteração de estado: de {$incidentStatusHistory['status']} para {$incident->status}";
        $topic = self::buildIncidentTopic($incident->id, false);
        self::send($status, $incident->location, $incident->id, $topic);
    }

    public static function sendNewFireNotification(Incident $incident)
    {
        $topic = self::buildNewFireTopic($incident);
        $status = "Novo incêndio em {$incident->location}";
        $data = self::fireData($incident->id);
        $data['isFire'] = '1';
        self::sendToCondition($topic, $incident->location, $status, $data);
    }

    /**
     * Send notification for any new incident to "all incidents" district subscribers.
     */
    public static function sendNewIncidentNotification(Incident $incident)
    {
        $topic = self::buildAllIncidentsTopic($incident);
        $nature = $incident->natureza ? " — {$incident->natureza}" : '';
        $status = $incident->isFire
            ? "Novo incêndio em {$incident->location}"
            : "Nova ocorrência em {$incident->location}{$nature}";
        $data = self::fireData($incident->id);
        $data['isFire'] = $incident->isFire ? '1' : '0';
        self::sendToCondition($topic, $incident->location, $status, $data);
    }

    public static function sendWarningMadeiraNotification($title, $description)
    {
        $p = self::prefix();
        $condition = "'{$p}madeira' in topics";
        if (env('LEGACY_ENABLE')) {
            $condition .= " || 'Madeira' in topics";
        }
        self::sendToCondition($condition, $title, $description);
    }

    public static function sendPlaneNotification($status)
    {
        $topic = self::buildPlanesTopic();
        self::sendToCondition($topic, 'Meio Aéreo', $status);
    }

    public static function sendWarningNotification($status)
    {
        self::sendWarning($status);
    }

    public static function sendAllNotification($status)
    {
        self::sendToTopic('all', 'Alerta', $status);
    }

    /**
     * Send a data-only message for nearby proximity checks.
     * The mobile app calculates distance locally — no user location is sent to the server.
     */
    public static function sendNearbyNotification(Incident $incident)
    {
        $p = self::prefix();
        $topic = "{$p}incident-nearby";

        self::sendDataOnlyToTopic($topic, [
            'type'     => 'nearby',
            'fireId'   => (string) $incident->id,
            'lat'      => (string) $incident->lat,
            'lng'      => (string) $incident->lng,
            'location' => (string) $incident->location,
            'nature'   => (string) ($incident->natureza ?? ''),
            'isFire'   => $incident->isFire ? '1' : '0',
        ]);
    }
}

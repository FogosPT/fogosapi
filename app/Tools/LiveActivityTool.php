<?php

namespace App\Tools;

use App\Jobs\SendLiveActivityPush;
use App\Models\Incident;
use App\Models\LiveActivityToken;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;

class LiveActivityTool
{
    private const JWT_TTL_SECONDS = 3300; // 55 min — APNs rejects tokens older than 60 min
    private const STALE_AFTER_SECONDS      = 3600;
    private const DISMISSAL_AFTER_SECONDS  = 3600;
    private const EXPIRATION_AFTER_SECONDS = 3600;

    public static function pushUpdate(Incident $incident): void
    {
        if (!env('LIVE_ACTIVITY_ENABLE')) {
            return;
        }

        $tokens = self::tokensFor($incident);
        if ($tokens->isEmpty()) {
            return;
        }

        $now     = time();
        $payload = [
            'aps' => [
                'timestamp'     => $now,
                'event'         => 'update',
                'content-state' => self::contentState($incident, $now),
                'stale-date'    => $now + self::STALE_AFTER_SECONDS,
            ],
        ];

        foreach ($tokens as $t) {
            dispatch(new SendLiveActivityPush($payload, (string) $t->push_token, (string) $t->env));
        }
    }

    public static function pushEnd(Incident $incident): void
    {
        if (!env('LIVE_ACTIVITY_ENABLE')) {
            return;
        }

        $tokens = self::tokensFor($incident);
        if ($tokens->isEmpty()) {
            return;
        }

        $now     = time();
        $payload = [
            'aps' => [
                'timestamp'      => $now,
                'event'          => 'end',
                'content-state'  => self::contentState($incident, $now),
                'dismissal-date' => $now + self::DISMISSAL_AFTER_SECONDS,
            ],
        ];

        foreach ($tokens as $t) {
            dispatch(new SendLiveActivityPush($payload, (string) $t->push_token, (string) $t->env));
        }

        LiveActivityToken::where('fire_id', $incident->id)->delete();
    }

    public static function apnsJwt(): string
    {
        return Cache::remember('apns:jwt', self::JWT_TTL_SECONDS, function () {
            $teamId = config('apns.team_id');
            $keyId  = config('apns.key_id');
            $keyPath = config('apns.private_key');

            $key = file_get_contents($keyPath);
            if ($key === false) {
                throw new \RuntimeException("APNs private key not readable at {$keyPath}");
            }

            return JWT::encode(
                ['iss' => $teamId, 'iat' => time()],
                $key,
                'ES256',
                $keyId
            );
        });
    }

    public static function expirationTimestamp(): int
    {
        return time() + self::EXPIRATION_AFTER_SECONDS;
    }

    private static function tokensFor(Incident $incident)
    {
        return LiveActivityToken::where('fire_id', $incident->id)->get();
    }

    private static function contentState(Incident $incident, int $now): array
    {
        return [
            'statusText'     => (string) ($incident->status ?? ''),
            'statusColorHex' => self::hexColor($incident->statusColor),
            'human'          => (int) ($incident->man ?? 0),
            'terrain'        => (int) ($incident->terrain ?? 0),
            'aerial'         => (int) ($incident->aerial ?? 0),
            'distanceKm'     => null,
            'updatedAt'      => $now,
        ];
    }

    private static function hexColor(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '#000000';
        }
        return str_starts_with($raw, '#') ? $raw : ('#' . $raw);
    }
}

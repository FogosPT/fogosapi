<?php

namespace App\Tools;

use App\Jobs\SendLiveActivityPush;
use App\Models\Incident;
use App\Models\LiveActivityToken;
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
            return self::signJwt();
        });
    }

    private static function signJwt(): string
    {
        $keyId  = (string) config('apns.key_id');
        $teamId = (string) config('apns.team_id');
        $keyPath = (string) config('apns.private_key');

        $keyPem = file_get_contents($keyPath);
        if ($keyPem === false) {
            throw new \RuntimeException("APNs private key not readable at {$keyPath}");
        }

        $key = openssl_pkey_get_private($keyPem);
        if ($key === false) {
            throw new \RuntimeException('APNs private key could not be parsed');
        }

        $header  = ['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT'];
        $claims  = ['iss' => $teamId, 'iat' => time()];
        $signing = self::b64u((string) json_encode($header)) . '.' . self::b64u((string) json_encode($claims));

        $derSignature = '';
        if (!openssl_sign($signing, $derSignature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to sign APNs JWT');
        }

        return $signing . '.' . self::b64u(self::derToJose($derSignature));
    }

    private static function b64u(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * ECDSA P-256 signatures come out of openssl_sign in DER form.
     * APNs (JOSE) expects raw R||S — 32 bytes each, big-endian. DER wraps
     * each integer with a leading 0x00 if the high bit is set, which we
     * strip before left-padding to 32 bytes.
     */
    private static function derToJose(string $der): string
    {
        $offset = 2; // skip SEQUENCE header (0x30 <len>)

        if (($der[$offset] ?? '') !== "\x02") {
            throw new \RuntimeException('Malformed DER signature: expected INTEGER for R');
        }
        $offset++;
        $rLen = ord($der[$offset++]);
        $r    = substr($der, $offset, $rLen);
        $offset += $rLen;

        if (($der[$offset] ?? '') !== "\x02") {
            throw new \RuntimeException('Malformed DER signature: expected INTEGER for S');
        }
        $offset++;
        $sLen = ord($der[$offset++]);
        $s    = substr($der, $offset, $sLen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
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

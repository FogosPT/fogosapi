<?php

namespace App\Tools;

use Illuminate\Support\Facades\Log;

class DiscordTool
{
    // Discord rejects messages above 2000 characters.
    private const MAX_LENGTH = 2000;

    /**
     * Tests set this to [] to capture postSeismic() messages instead of
     * calling Discord.
     *
     * @var null|string[]
     */
    public static ?array $sent = null;

    public static function post($message)
    {
        if (!env('DISCORD_ENABLE')) {
            return;
        }

        self::send(env('DISCORD_WEBHOOK_ID'), env('DISCORD_WEBHOOK_TOKEN'), $message);
    }

    public static function postAero($message)
    {
        if (!env('DISCORD_ENABLE')) {
            return;
        }

        self::send(env('DISCORD_WEBHOOK_ID_AERO'), env('DISCORD_WEBHOOK_TOKEN_AERO'), $message);
    }

    /**
     * sismos.pt earthquake alerts, on their own webhook (not the Fogos.pt
     * channels). Gated by SEISMIC_NOTIFICATIONS_ENABLE in the caller.
     *
     * @param mixed $message
     */
    public static function postSeismic($message)
    {
        if (self::$sent !== null) {
            self::$sent[] = (string) $message;

            return;
        }

        self::send(env('SEISMIC_DISCORD_WEBHOOK_ID'), env('SEISMIC_DISCORD_WEBHOOK_TOKEN'), $message);
    }

    /**
     * Ops alerts. Not gated by DISCORD_ENABLE (errors are wanted even when
     * public channels are off); an unset webhook disables it.
     *
     * @param mixed $message
     */
    public static function postError($message)
    {
        self::send(env('DISCORD_WEBHOOK_ID_ERRORS'), env('DISCORD_WEBHOOK_TOKEN_ERRORS'), $message);
    }

    /**
     * JSON body (the old form body was not encoded: `&`, `+` or `#` in the
     * message broke or injected fields) and no mention parsing, so text
     * coming from upstream feeds cannot ping @everyone.
     *
     * @param mixed $webhookId
     * @param mixed $webhookToken
     * @param mixed $message
     */
    private static function send($webhookId, $webhookToken, $message): void
    {
        if (empty($webhookId) || empty($webhookToken)) {
            return;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://discord.com/api/webhooks/{$webhookId}/{$webhookToken}");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'content' => mb_substr((string) $message, 0, self::MAX_LENGTH),
            'allowed_mentions' => ['parse' => []],
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if (curl_exec($ch) === false) {
            Log::warning('Discord webhook failed: '.curl_error($ch));
        }
        curl_close($ch);
    }
}

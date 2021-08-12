<?php

namespace App\Tools;

class DiscordTool
{
    public static function post($message)
    {
        if (!env('DISCORD_ENABLE')) {
            return;
        }

        $webhookId = env('DISCORD_WEBHOOK_ID');
        $webhookToken = env('DISCORD_WEBHOOK_TOKEN');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://discordapp.com/api/webhooks/{$webhookId}/{$webhookToken}");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            "content={$message}"
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
        curl_close($ch);
    }

    public static function postAero($message)
    {
        if (!env('DISCORD_ENABLE')) {
            return;
        }

        $webhookId = env('DISCORD_WEBHOOK_ID_AERO');
        $webhookToken = env('DISCORD_WEBHOOK_TOKEN_AERO');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://discordapp.com/api/webhooks/{$webhookId}/{$webhookToken}");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            "content={$message}"
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
        curl_close($ch);
    }
}

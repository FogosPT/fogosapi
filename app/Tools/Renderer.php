<?php

namespace App\Tools;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class Renderer
{
    public static function capture(
        string $urlPath,
        ?int $width = null,
        ?int $height = null,
        ?string $waitFor = null
    ): ?RendererCapture {
        $rendererUrl = rtrim(env('RENDERER_URL', 'http://fogos-renderer:3000'), '/');
        $timeoutMs   = (int) env('RENDERER_TIMEOUT_MS', 20000);
        $retries     = max(1, (int) env('RENDERER_RETRIES', 3));
        $minBytes    = (int) env('RENDERER_MIN_BYTES', 8192);

        $width  = $width  ?: (int) env('SCREENSHOT_WIDTH', 1000);
        $height = $height ?: (int) env('SCREENSHOT_HEIGHT', 1300);
        $domain = env('SCREENSHOT_DOMAIN');

        $target = 'https://' . $domain . '/' . ltrim($urlPath, '/');

        $payload = [
            'url'      => $target,
            'width'    => $width,
            'height'   => $height,
            'minBytes' => $minBytes,
        ];
        if ($waitFor) {
            $payload['waitFor'] = $waitFor;
        }

        $client = new Client([
            'base_uri' => $rendererUrl,
            'timeout'  => $timeoutMs / 1000,
        ]);

        $lastError = null;
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $response = $client->post('/render', [
                    'json'        => $payload,
                    'http_errors' => true,
                ]);
                $bytes = (string) $response->getBody();
                if (strlen($bytes) < $minBytes) {
                    throw new \RuntimeException('renderer returned ' . strlen($bytes) . ' bytes (< minBytes)');
                }
                return self::persist($bytes);
            } catch (GuzzleException|\Throwable $e) {
                $lastError = $e;
                Log::warning('Renderer attempt ' . $attempt . ' failed: ' . $e->getMessage(), ['url' => $target]);
                if ($attempt < $retries) {
                    usleep((int) (pow(3, $attempt - 1) * 1_000_000));
                }
            }
        }

        $msg = '🖼️ Renderer falhou para ' . $target . ' após ' . $retries . ' tentativas: ' . ($lastError ? $lastError->getMessage() : 'unknown');
        try {
            DiscordTool::postError($msg);
        } catch (\Throwable $e) {
            Log::error('Discord postError failed: ' . $e->getMessage());
        }
        Log::error($msg);

        return null;
    }

    private static function persist(string $bytes): RendererCapture
    {
        $dir = storage_path('app/tmp/screenshots');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . Str::uuid()->toString() . '.png';
        file_put_contents($path, $bytes);
        return new RendererCapture($path, $bytes);
    }
}

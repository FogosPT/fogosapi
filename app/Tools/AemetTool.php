<?php

namespace App\Tools;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Client for the AEMET OpenData API (Spanish weather service), base
 * `https://opendata.aemet.es/opendata/api/`, key in the `api_key` header
 * (`config('services.aemet.key')`).
 *
 * Every endpoint is a **two-step request**: the base path returns
 * `{descripcion, estado, datos, metadatos}` and the actual payload is
 * fetched from the short-lived `datos` URL straight after. `estado` != 200
 * is a failure. Without a key the API answers HTTP 200 with an empty body;
 * with an invalid/expired key, HTTP 401 — both are failures, the 401 one
 * flagged via `AemetException::$unauthorized`.
 *
 * JSON responses come back in ISO-8859-15 and are converted to UTF-8
 * before `json_decode`; the warnings tar is returned as raw bytes.
 */
class AemetTool
{
    public const BASE_URL = 'https://opendata.aemet.es/opendata/api/';

    private Client $client;

    private string $apiKey;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'http_errors' => false,
            'timeout' => 20, // headroom under the jobs' 80 s timeout (two requests)
            'connect_timeout' => 5,
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
            ],
        ]);
        $this->apiKey = (string) config('services.aemet.key');
    }

    /** Raw bytes of the `avisos_cap/ultimoelaborado/area/esp` tar (GNU, uncompressed). */
    public function warningsTar(): string
    {
        $datosUrl = $this->step1('avisos_cap/ultimoelaborado/area/esp');

        return $this->fetchRaw($datosUrl);
    }

    /** All rows of the last ~12h of conventional station observations. */
    public function observations(): array
    {
        return $this->fetchJson('observacion/convencional/todas');
    }

    /** The full station inventory. */
    public function inventory(): array
    {
        return $this->fetchJson('valores/climatologicos/inventarioestaciones/todasestaciones');
    }

    /** Daily climatological summaries for the calendar dates `[from, to]` (whole days, as AEMET's UTC range). */
    public function daily(Carbon $from, Carbon $to): array
    {
        $path = sprintf(
            'valores/climatologicos/diarios/datos/fechaini/%s/fechafin/%s/todasestaciones',
            $from->format('Y-m-d').'T00:00:00UTC',
            $to->format('Y-m-d').'T23:59:59UTC'
        );

        return $this->fetchJson($path);
    }

    /** Runs the two-step request and decodes the `datos` payload as UTF-8 JSON. */
    private function fetchJson(string $path): array
    {
        $datosUrl = $this->step1($path);
        $body = $this->fetchRaw($datosUrl);

        $decoded = json_decode(mb_convert_encoding($body, 'UTF-8', 'ISO-8859-15'), true);
        if (!is_array($decoded)) {
            throw new AemetException('A AEMET devolveu dados inválidos.');
        }

        return $decoded;
    }

    /**
     * Step 1: requests `$path` with the API key and returns the `datos` URL
     * from the `{descripcion, estado, datos, metadatos}` envelope.
     */
    private function step1(string $path): string
    {
        $response = $this->request(self::BASE_URL.$path, ['api_key' => $this->apiKey]);

        if ($response->getStatusCode() === 401) {
            throw new AemetException('Pedido recusado: chave AEMET inválida ou expirada (renovar em https://opendata.aemet.es/centrodedescargas/altaUsuario).', unauthorized: true);
        }

        if ($response->getStatusCode() !== 200) {
            throw new AemetException('A AEMET devolveu HTTP '.$response->getStatusCode().'.');
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            throw new AemetException('A AEMET devolveu uma resposta vazia (chave em falta?).');
        }

        $decoded = json_decode(mb_convert_encoding($body, 'UTF-8', 'ISO-8859-15'), true);
        if (!is_array($decoded)) {
            throw new AemetException('A AEMET devolveu uma resposta inválida.');
        }

        if ((int) ($decoded['estado'] ?? 0) !== 200) {
            $reason = $decoded['descripcion'] ?? ('estado '.($decoded['estado'] ?? '?'));

            throw new AemetException((string) $reason);
        }

        if (empty($decoded['datos']) || !is_string($decoded['datos'])) {
            throw new AemetException('A AEMET não devolveu o URL dos dados.');
        }

        return $decoded['datos'];
    }

    /** Step 2: fetches the (short-lived) `datos` URL and returns the raw body. */
    private function fetchRaw(string $datosUrl): string
    {
        $response = $this->request($datosUrl);

        if ($response->getStatusCode() !== 200) {
            throw new AemetException('A AEMET devolveu HTTP '.$response->getStatusCode().' ao obter os dados.');
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            throw new AemetException('A AEMET devolveu dados vazios.');
        }

        return $body;
    }

    private function request(string $url, array $headers = []): ResponseInterface
    {
        try {
            return $this->client->request('GET', $url, [
                'headers' => $headers + ['User-Agent' => 'Fogos.pt/3.0'],
            ]);
        } catch (GuzzleException $e) {
            throw new AemetException('O pedido à AEMET falhou: '.$e->getMessage());
        }
    }
}

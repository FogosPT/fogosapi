<?php

namespace App\Tools;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class ICNFTool
{
    public const TABLE_URL = 'https://fogos.icnf.pt/localizador/faztable.asp';

    public const ESTADO_EXTINTO = 'Extinto';
    public const ESTADO_DOMINADO = 'Dominado';

    /**
     * ICNF keeps fires as "Dominado" for days/months before closing them.
     * Past this window a "Dominado" fire is not considered active.
     */
    public const DOMINADO_ACTIVE_HOURS = 24;

    /**
     * Fetches the ICNF occurrences table. Throws on HTTP failures.
     *
     * @return array<int, array{id: string, estado: string, startedAt: ?Carbon}>
     */
    public static function fetchOccurrences(): array
    {
        $options = [
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
            ],
            'verify' => false,
            'connect_timeout' => 10,
            'timeout' => 30,
        ];

        $client = new Client();
        $res = $client->request('GET', self::TABLE_URL, $options);

        return self::parseTable($res->getBody()->getContents());
    }

    /**
     * Parses the google.visualization rows embedded in faztable.asp.
     * The first two bracket matches are the chart package list and the
     * column header; every following match is one occurrence.
     *
     * @return array<int, array{id: string, estado: string, startedAt: ?Carbon}>
     */
    public static function parseTable(string $html): array
    {
        $html = str_replace(PHP_EOL, '', $html);

        preg_match_all('/\[(.*?)\]/', $html, $result);

        $occurrences = [];
        $skippedIds = [];

        foreach (array_slice($result[1], 2) as $r) {
            $rr = explode("',", $r);

            $id = strip_tags(str_replace("'", '', $rr[0]));

            if (!ctype_digit($id)) {
                $skippedIds[$id] = ($skippedIds[$id] ?? 0) + 1;

                continue;
            }

            $occurrences[] = [
                'id' => $id,
                'estado' => isset($rr[12]) ? ltrim($rr[12], "'") : '',
                'startedAt' => isset($rr[2]) ? self::parseStart(ltrim($rr[2], "'")) : null,
            ];
        }

        if (!empty($skippedIds)) {
            Log::debug('ICNF: skipped non-numeric ids', [
                'total' => array_sum($skippedIds),
                'unique' => $skippedIds,
            ]);
        }

        return $occurrences;
    }

    /**
     * @return array{0: string, 1: int, 2: string} [status, statusCode, statusColor]
     */
    public static function mapStatus(string $estado): array
    {
        if ($estado === self::ESTADO_EXTINTO) {
            return ['Conclusão', 8, '8e7e7d'];
        }

        if ($estado === self::ESTADO_DOMINADO) {
            return ['Em Resolução', 7, '65C4ED'];
        }

        return ['Em Curso', 5, 'B81E1F'];
    }

    /**
     * Whether an ICNF occurrence should be shown as active when ICNF is the
     * primary source.
     */
    public static function isActive(string $estado, ?Carbon $startedAt): bool
    {
        if ($estado === self::ESTADO_EXTINTO) {
            return false;
        }

        if ($estado === self::ESTADO_DOMINADO) {
            return $startedAt !== null
                && $startedAt->diffInHours(Carbon::now()) <= self::DOMINADO_ACTIVE_HOURS;
        }

        return true;
    }

    private static function parseStart(string $raw): ?Carbon
    {
        try {
            $parsed = Carbon::createFromFormat('d/m/Y H:i:s', trim($raw), 'Europe/Lisbon');
        } catch (\Throwable $e) {
            return null;
        }

        return $parsed === false ? null : $parsed;
    }
}

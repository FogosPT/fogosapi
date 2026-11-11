<?php

namespace App\Jobs;

use App\Models\MadeiraEvent;
use App\Models\MadeiraEventSnapshot;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Polls the SRPC-RAM (Madeira) events feed (`MADEIRA_EVENTS_URL`, kept out of
 * the code). The feed is a snapshot of the current adverse-weather event with
 * aggregates only (per municipality, nature and hour), so each run:
 *  - upserts the event in `madeiraEvents` (latest aggregates);
 *  - adds one KPI row to `madeiraEventSnapshots` per new `atualizado`;
 *  - closes every other event still flagged active (the feed only carries one).
 * Feed times are Madeira local time. A feed older than what is stored (cached
 * response) is ignored.
 */
class ProcessMadeiraEvents extends Job
{
    public const TIMEZONE = 'Atlantic/Madeira';

    public $timeout = 60;

    public function __construct() {}

    /** Only set synchronously (tests); queued jobs build their own. */
    private ?Client $client = null;

    public function withClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function handle(): void
    {
        $url = (string) env('MADEIRA_EVENTS_URL');
        if ($url === '') {
            Log::debug('[ProcessMadeiraEvents] MADEIRA_EVENTS_URL not set, skipping.');

            return;
        }

        $client = $this->client ?? new Client([
            'timeout' => 20,
            'connect_timeout' => 5,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
                'Accept' => 'application/json',
            ],
        ]);

        try {
            $response = $client->get($url);
        } catch (\Throwable $e) {
            Log::warning('[ProcessMadeiraEvents] request failed: '.$e->getMessage());

            return;
        }

        if ($response->getStatusCode() !== 200) {
            Log::warning('[ProcessMadeiraEvents] feed returned '.$response->getStatusCode());

            return;
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload)) {
            Log::warning('[ProcessMadeiraEvents] feed returned invalid JSON');

            return;
        }

        $this->sync($payload);
    }

    public function sync(array $payload): ?MadeiraEvent
    {
        $evento = $payload['evento'] ?? null;
        $start = $this->time($payload['periodo']['inicio'] ?? null);
        $updatedAt = $this->time($payload['atualizado'] ?? null);

        if (!is_array($evento) || empty($evento['titulo']) || !$start || !$updatedAt) {
            return null;
        }

        // Compared across events, so a cached copy of an earlier event can
        // neither rewrite it nor close the current one.
        $newest = MadeiraEvent::orderBy('sourceUpdatedAt', 'desc')->first();
        if ($newest?->sourceUpdatedAt && $updatedAt->lt($newest->sourceUpdatedAt)) {
            return null;
        }

        $eventId = md5($evento['titulo'].'|'.$start->format('Y-m-d\TH:i'));
        $event = MadeiraEvent::where('eventId', $eventId)->first() ?? new MadeiraEvent(['eventId' => $eventId]);

        $natureDescriptions = collect($payload['tipologias'] ?? [])
            ->mapWithKeys(fn ($t) => [(string) $t['codigo'] => $t['desc'] ?? null]);

        $kpi = $payload['kpi'] ?? [];

        $event->fill([
            'title' => $evento['titulo'],
            'zone' => $evento['zona'] ?? null,
            'status' => $evento['estado'] ?? null,
            'active' => ($evento['estado'] ?? null) === 'ATIVO',
            'recommendations' => array_values($evento['recomendacoes'] ?? []),
            'start' => $start,
            'end' => $this->time($payload['periodo']['fim'] ?? null),
            'untilNow' => (bool) ($payload['periodo']['ate_agora'] ?? false),
            'sourceUpdatedAt' => $updatedAt,
            'syncedAt' => $this->time($payload['sincronizado'] ?? null),
            'kpi' => [
                'occurrences' => $this->int($kpi['ocorrencias'] ?? null),
                'activeOccurrences' => $this->int($kpi['ativas'] ?? null),
                'operationals' => $this->int($kpi['operacionais'] ?? null),
                'groundAssets' => $this->int($kpi['meios_terrestres'] ?? null),
                'municipalities' => $this->int($kpi['concelhos_afetados'] ?? null),
                'peak' => isset($kpi['pico']['h']) ? [
                    'time' => $this->time($kpi['pico']['h'])?->toIso8601ZuluString(),
                    'occurrences' => $this->int($kpi['pico']['n'] ?? null),
                ] : null,
            ],
            'municipalities' => collect($payload['concelhos'] ?? [])->map(fn ($c) => [
                'name' => $c['concelho'] ?? null,
                'occurrences' => $this->int($c['oc'] ?? null),
                'operationals' => $this->int($c['op'] ?? null),
                'groundAssets' => $this->int($c['mt'] ?? null),
                'natures' => collect($c['tip'] ?? [])->map(fn ($n, $code) => [
                    'code' => (string) $code,
                    'description' => $natureDescriptions->get((string) $code),
                    'occurrences' => $this->int($n),
                ])->values()->all(),
            ])->values()->all(),
            'natures' => collect($payload['tipologias'] ?? [])->map(fn ($t) => [
                'code' => (string) $t['codigo'],
                'description' => $t['desc'] ?? null,
                'occurrences' => $this->int($t['n'] ?? null),
                'percentage' => isset($t['pct']) ? (float) $t['pct'] : null,
            ])->values()->all(),
            'hourly' => collect($payload['horas'] ?? [])->map(fn ($h) => [
                'time' => $this->time($h['h'] ?? null)?->toIso8601ZuluString(),
                'occurrences' => $this->int($h['n'] ?? null),
                'cumulative' => $this->int($h['acum'] ?? null),
            ])->values()->all(),
            'roadRestrictions' => collect($evento['condicionamentos'] ?? [])->map(fn ($c) => [
                'road' => $c['via'] ?? null,
                'closed' => ($c['nivel'] ?? null) === 'cortada',
                'text' => $c['texto'] ?? null,
                'time' => $this->time($c['data'] ?? null)?->toIso8601ZuluString(),
            ])->values()->all(),
            'communiques' => $this->communiques($evento['comunicados'] ?? [], $payload['oficiais'] ?? []),
            'summary' => $payload['resumo'] ?? null,
            'notes' => $payload['notas'] ?? null,
            'source' => $payload['fonte'] ?? null,
        ]);
        $event->save();

        MadeiraEventSnapshot::updateOrCreate(
            ['eventId' => $eventId, 'time' => $updatedAt],
            [
                'occurrences' => $event->kpi['occurrences'],
                'activeOccurrences' => $event->kpi['activeOccurrences'],
                'operationals' => $event->kpi['operationals'],
                'groundAssets' => $event->kpi['groundAssets'],
                'municipalities' => $event->kpi['municipalities'],
            ]
        );

        // The feed only carries the current event: any other one still
        // flagged active has ended (or was replaced by a new start time).
        MadeiraEvent::where('active', true)
            ->where('eventId', '!=', $eventId)
            ->update(['active' => false]);

        return $event;
    }

    /**
     * The event's own communiqués plus the latest official SRPC-RAM ones
     * (`oficiais`, procivmadeira.pt), newest first, as the dashboard shows
     * them. The same communiqué in both lists is kept once.
     */
    private function communiques(array $event, array $official): array
    {
        // Official first, so it wins when the same one is in both lists.
        return collect($official)->map(fn ($c) => ['oficial' => true] + $c)
            ->concat(collect($event)->map(fn ($c) => $c + ['oficial' => false]))
            ->filter(fn ($c) => !empty($c['titulo']))
            ->map(fn ($c) => [
                'title' => $c['titulo'],
                'time' => $this->time($c['data'] ?? null)?->toIso8601ZuluString(),
                'text' => $c['texto'] ?? null,
                'link' => $c['link'] ?? null,
                'official' => (bool) $c['oficial'],
            ])
            ->unique(fn ($c) => $c['link'] ?? $c['title'].'|'.$c['time'])
            ->sortByDesc('time')
            ->values()
            ->all();
    }

    private function time(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value, self::TIMEZONE)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function int($value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}

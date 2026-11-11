<?php

namespace App\Jobs;

use App\Models\MadeiraOccurrence;
use App\Models\MadeiraOccurrenceRevision;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Polls the public statistics view of the SRPC-RAM (Madeira) ArcGIS
 * (`MADEIRA_OCCURRENCES_URL`, the layer URL, kept out of the code): one row
 * per occurrence, without geometry, status or occurrence number. Each run
 * reads the whole table (paginated) and:
 *  - creates/updates `madeiraOccurrences` (current version, by objectid);
 *  - adds a `madeiraOccurrenceRevisions` row for every new or changed version;
 *  - flags rows no longer published with `removedAt` (and a revision).
 * A run that fails half-way, or returns far fewer rows than we hold, writes
 * no removals, so a bad response never wipes the table.
 */
class ProcessMadeiraOccurrences extends Job
{
    private const PAGE_SIZE = 2000;
    private const MAX_PAGES = 50;

    public $timeout = 120;

    public int $created = 0;
    public int $updated = 0;
    public int $removed = 0;

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
        $url = rtrim((string) env('MADEIRA_OCCURRENCES_URL'), '/');
        if ($url === '') {
            Log::debug('[ProcessMadeiraOccurrences] MADEIRA_OCCURRENCES_URL not set, skipping.');

            return;
        }

        $rows = $this->fetch($url);
        if ($rows === null) {
            return;
        }

        $this->sync($rows);
    }

    /**
     * Every row of the table, or null when any page fails.
     */
    private function fetch(string $url): ?array
    {
        $client = $this->client ?? new Client([
            'timeout' => 30,
            'connect_timeout' => 5,
            'http_errors' => false,
            'headers' => ['User-Agent' => 'Fogos.pt/3.0', 'Accept' => 'application/json'],
        ]);

        $rows = [];
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            try {
                $response = $client->get($url.'/query', ['query' => [
                    'where' => '1=1',
                    'outFields' => '*',
                    'orderByFields' => 'objectid ASC',
                    'resultOffset' => $page * self::PAGE_SIZE,
                    'resultRecordCount' => self::PAGE_SIZE,
                    'f' => 'json',
                ]]);
            } catch (\Throwable $e) {
                Log::warning('[ProcessMadeiraOccurrences] request failed: '.$e->getMessage());

                return null;
            }

            $data = json_decode((string) $response->getBody(), true);
            // ArcGIS reports errors as `{"error": …}` behind a 200.
            if ($response->getStatusCode() !== 200 || !is_array($data) || isset($data['error']) || !isset($data['features'])) {
                Log::warning('[ProcessMadeiraOccurrences] invalid response', ['status' => $response->getStatusCode(), 'page' => $page]);

                return null;
            }

            foreach ($data['features'] as $feature) {
                if (isset($feature['attributes']['objectid'])) {
                    $rows[] = $feature['attributes'];
                }
            }

            if (empty($data['exceededTransferLimit']) || !$data['features']) {
                return $rows;
            }
        }

        Log::warning('[ProcessMadeiraOccurrences] too many pages, skipping run');

        return null;
    }

    /**
     * @param array $rows raw ArcGIS attributes of the whole table
     */
    public function sync(array $rows): void
    {
        if (!$rows) {
            return;
        }

        $existing = MadeiraOccurrence::all()->keyBy('sourceId');
        $seen = [];

        foreach ($rows as $row) {
            $id = (int) $row['objectid'];
            $seen[$id] = true;

            ksort($row);
            $hash = sha1(json_encode($row));

            $occurrence = $existing->get($id);
            if ($occurrence && $occurrence->hash === $hash && !$occurrence->removedAt) {
                continue;
            }

            $occurrence ??= new MadeiraOccurrence(['sourceId' => $id]);
            $isNew = !$occurrence->exists;

            $occurrence->fill($this->normalize($row) + ['hash' => $hash, 'removedAt' => null]);
            $occurrence->save();

            MadeiraOccurrenceRevision::create(['sourceId' => $id, 'hash' => $hash, 'payload' => $row, 'removed' => false]);

            $isNew ? $this->created++ : $this->updated++;
        }

        $published = $existing->filter(fn (MadeiraOccurrence $o) => !$o->removedAt);

        // A short (but valid) response would otherwise remove most of the table.
        if (count($rows) < $published->count() / 2) {
            Log::warning('[ProcessMadeiraOccurrences] response much smaller than stored, not removing', [
                'rows' => count($rows),
                'stored' => $published->count(),
            ]);

            return;
        }

        foreach ($published as $id => $occurrence) {
            if (isset($seen[$id])) {
                continue;
            }

            $occurrence->removedAt = Carbon::now();
            $occurrence->save();

            MadeiraOccurrenceRevision::create(['sourceId' => $id, 'hash' => null, 'payload' => null, 'removed' => true]);
            $this->removed++;
        }
    }

    private function normalize(array $row): array
    {
        $parishCode = $this->string($row['codigocaop'] ?? null);
        $municipalityCode = $parishCode ? substr($parishCode, 0, 4) : null;

        return [
            'start' => $this->time($row['inicio'] ?? null),
            'natureCode' => $this->string($row['codigo'] ?? null),
            'nature' => $this->string($row['tipologiafonte'] ?? null),
            'firefighters' => is_numeric($row['bombeiros'] ?? null) ? (int) $row['bombeiros'] : null,
            'vehicles' => is_numeric($row['viaturas'] ?? null) ? (int) $row['viaturas'] : null,
            'parishCode' => $parishCode,
            'parish' => $this->string($row['freguesia'] ?? null),
            'municipalityCode' => $municipalityCode,
            'municipality' => MadeiraOccurrence::MUNICIPALITIES[$municipalityCode] ?? null,
            'entities' => collect(preg_split('/[,;]/', (string) ($row['entidades'] ?? '')))
                ->map(fn ($e) => trim($e))->filter()->unique()->values()->all(),
            'sourceUpdatedAt' => $this->time($row['atualizadoem'] ?? null),
        ];
    }

    /** ArcGIS dates are epoch milliseconds, UTC. */
    private function time($value): ?Carbon
    {
        return is_numeric($value) ? Carbon::createFromTimestampMs((int) $value, 'UTC') : null;
    }

    private function string($value): ?string
    {
        $value = is_string($value) || is_numeric($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }
}

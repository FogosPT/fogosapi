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
 * per occurrence, without geometry, status or occurrence number.
 *
 * The objectid is NOT an identifier: the view is synced by position, so when
 * a row is added or edited the following rows shift to other objectids.
 * Rows are therefore stored by content (`key` = sha1 of every attribute but
 * objectid and the sync time `atualizadoem`; `copies` = identical rows), and
 * each occurrence gets its own stable `occurrenceId`.
 *
 * Each run diffs the whole table (paginated) against the published contents.
 * A content that disappears and one that appears in the same run are the
 * same occurrence edited when they are the only pair with the same start and
 * nature (means, parish or corporations changed) or, failing that, the same
 * start (nature reclassified): the new content inherits the `occurrenceId`
 * and the old one gets `removedAt` + `replacedBy`. Everything else is a new
 * occurrence or a removal. Every change is logged in
 * `madeiraOccurrenceRevisions` (added / changed with a field diff / removed /
 * restored / copies).
 *
 * The endpoints serve the published contents. A run that fails half-way, or
 * returns far fewer rows than we hold, writes nothing.
 */
class ProcessMadeiraOccurrences extends Job
{
    private const PAGE_SIZE = 2000;
    private const MAX_PAGES = 50;

    public $timeout = 120;

    public int $created = 0;
    public int $changed = 0;
    public int $updated = 0;
    public int $removed = 0;

    /** Fields compared in the `changes` diff of a `changed` revision. */
    private const DIFF_FIELDS = ['start', 'natureCode', 'nature', 'firefighters', 'vehicles', 'parishCode', 'parish', 'entities'];

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

        // Identical rows (same time, nature, parish, means…) are one content
        // with `copies`.
        $incoming = [];
        foreach ($rows as $row) {
            $key = self::key($row);
            $incoming[$key] ??= ['row' => $row, 'copies' => 0];
            $incoming[$key]['copies']++;
            if (($row['atualizadoem'] ?? 0) > ($incoming[$key]['row']['atualizadoem'] ?? 0)) {
                $incoming[$key]['row']['atualizadoem'] = $row['atualizadoem'];
            }
        }

        $existing = MadeiraOccurrence::all()->keyBy('key');
        $published = $existing->filter(fn (MadeiraOccurrence $o) => !$o->removedAt);

        // A short (but valid) response would otherwise remove most of the table.
        if (count($incoming) < $published->count() / 2) {
            Log::warning('[ProcessMadeiraOccurrences] response much smaller than stored, skipping run', [
                'rows' => count($incoming),
                'stored' => $published->count(),
            ]);

            return;
        }

        // Contents that appear (new or back after a removal) and disappear.
        $appeared = [];
        foreach ($incoming as $key => ['row' => $row, 'copies' => $copies]) {
            $occurrence = $existing->get($key);

            if (!$occurrence || $occurrence->removedAt) {
                $occurrence ??= new MadeiraOccurrence(['key' => $key]);
                $occurrence->fill($this->normalize($row) + ['copies' => $copies, 'removedAt' => null, 'replacedBy' => null]);
                $appeared[$key] = ['occurrence' => $occurrence, 'row' => $row];

                continue;
            }

            $updatedAt = $this->time($row['atualizadoem'] ?? null);
            if ($occurrence->copies === $copies && $occurrence->sourceUpdatedAt?->getTimestampMs() === $updatedAt?->getTimestampMs()) {
                continue;
            }

            if ($occurrence->copies !== $copies) {
                $this->log($occurrence, 'copies', ['copies' => $copies]);
                $this->updated++;
            }

            $occurrence->fill(['copies' => $copies, 'sourceUpdatedAt' => $updatedAt])->save();
        }

        $disappeared = $published->reject(fn (MadeiraOccurrence $o, $key) => isset($incoming[$key]));

        $links = $this->link(collect($appeared)->pluck('occurrence'), $disappeared->values());

        foreach ($appeared as $key => ['occurrence' => $occurrence, 'row' => $row]) {
            $previous = $links[$key] ?? null;
            $wasRemoved = $occurrence->exists;

            if ($previous) {
                $occurrence->occurrenceId = $previous->occurrenceId ?? $previous->key;
                $occurrence->previousKey = $previous->key;
            } else {
                $occurrence->occurrenceId ??= $key;
            }
            $occurrence->save();

            if ($previous) {
                $this->log($occurrence, 'changed', [
                    'previousKey' => $previous->key,
                    'changes' => $this->diff($previous, $occurrence),
                    'payload' => self::content($row),
                ]);
                $this->changed++;
            } elseif ($wasRemoved) {
                $this->log($occurrence, 'restored');
                $this->updated++;
            } else {
                $this->log($occurrence, 'added', ['payload' => self::content($row)]);
                $this->created++;
            }
        }

        $replacedBy = collect($links)->mapWithKeys(fn (MadeiraOccurrence $old, $newKey) => [$old->key => $newKey]);

        foreach ($disappeared as $key => $occurrence) {
            $occurrence->removedAt = Carbon::now();
            $occurrence->replacedBy = $replacedBy->get($key);
            $occurrence->save();

            if (!$occurrence->replacedBy) {
                $this->log($occurrence, 'removed', ['copies' => 0]);
                $this->removed++;
            }
        }
    }

    /**
     * Pairs appeared ↔ disappeared contents that are the same occurrence
     * edited: first by (start, nature), then by start alone, and only when
     * the pair is unique on both sides. Contents with copies are ambiguous
     * and never linked.
     *
     * @return array<string, MadeiraOccurrence> new key → previous version
     */
    private function link($appeared, $disappeared): array
    {
        $links = [];
        $taken = [];

        $passes = [
            fn (MadeiraOccurrence $o) => $o->start?->getTimestampMs().'|'.$o->natureCode,
            fn (MadeiraOccurrence $o) => (string) $o->start?->getTimestampMs(),
        ];

        foreach ($passes as $by) {
            $new = $appeared->filter(fn ($o) => $o->copies === 1 && !isset($links[$o->key]))->groupBy($by);
            $old = $disappeared->filter(fn ($o) => $o->copies === 1 && !isset($taken[$o->key]))->groupBy($by);

            foreach ($new as $group => $candidates) {
                if ($group === '' || $candidates->count() !== 1 || $old->get($group)?->count() !== 1) {
                    continue;
                }

                $previous = $old->get($group)->first();
                $links[$candidates->first()->key] = $previous;
                $taken[$previous->key] = true;
            }
        }

        return $links;
    }

    /** field => [before, after], for the fields that changed. */
    private function diff(MadeiraOccurrence $before, MadeiraOccurrence $after): array
    {
        $value = fn (MadeiraOccurrence $o, string $f) => $f === 'start' ? $o->start?->toIso8601ZuluString() : $o->{$f};

        $changes = [];
        foreach (self::DIFF_FIELDS as $field) {
            if ($value($before, $field) !== $value($after, $field)) {
                $changes[$field] = [$value($before, $field), $value($after, $field)];
            }
        }

        return $changes;
    }

    private function log(MadeiraOccurrence $occurrence, string $change, array $extra = []): void
    {
        MadeiraOccurrenceRevision::create(array_merge([
            'occurrenceId' => $occurrence->occurrenceId ?? $occurrence->key,
            'key' => $occurrence->key,
            'change' => $change,
            'copies' => $occurrence->copies,
        ], $extra));
    }

    /**
     * Content key: every attribute except the positional objectid and the
     * sync time.
     */
    public static function key(array $row): string
    {
        return sha1(json_encode(self::content($row)));
    }

    private static function content(array $row): array
    {
        unset($row['objectid'], $row['atualizadoem']);
        ksort($row);

        return $row;
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

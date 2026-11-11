<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessMadeiraOccurrences;
use App\Models\MadeiraOccurrence;
use App\Models\MadeiraOccurrenceRevision;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ProcessMadeiraOccurrencesTest extends TestCase
{
    use DatabaseMigrations;

    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUrl('https://madeira.test/FeatureServer/0');
    }

    protected function tearDown(): void
    {
        $this->setUrl(null);
        parent::tearDown();
    }

    /** See ProcessMadeiraEventsTest::setUrl(). */
    private function setUrl(?string $url): void
    {
        if ($url === null) {
            unset($_SERVER['MADEIRA_OCCURRENCES_URL'], $_ENV['MADEIRA_OCCURRENCES_URL']);
            putenv('MADEIRA_OCCURRENCES_URL');

            return;
        }

        $_SERVER['MADEIRA_OCCURRENCES_URL'] = $_ENV['MADEIRA_OCCURRENCES_URL'] = $url;
        putenv('MADEIRA_OCCURRENCES_URL='.$url);
    }

    /** @return array<int, array> raw attributes */
    private function rows(): array
    {
        $data = json_decode(file_get_contents(base_path('tests/fixtures/madeira/ocorrencias.json')), true);

        return array_column($data['features'], 'attributes');
    }

    private function page(array $rows, bool $more = false): Response
    {
        return new Response(200, [], json_encode([
            'features' => array_map(fn ($r) => ['attributes' => $r], $rows),
            'exceededTransferLimit' => $more,
        ]));
    }

    private function job(array $responses): ProcessMadeiraOccurrences
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return (new ProcessMadeiraOccurrences())->withClient(new Client(['http_errors' => false, 'handler' => $stack]));
    }

    private function row(int $objectid): array
    {
        return collect($this->rows())->firstWhere('objectid', $objectid);
    }

    /** @test */
    public function the_table_is_stored_as_one_occurrence_per_content_with_a_revision(): void
    {
        $rows = $this->rows();
        $job = $this->job([$this->page($rows)]);
        $job->handle();

        $this->assertSame(count($rows), MadeiraOccurrence::sum('copies'));
        $this->assertSame(MadeiraOccurrence::count(), MadeiraOccurrenceRevision::where('change', 'added')->count());
        $this->assertSame(MadeiraOccurrence::count(), $job->created);

        // objectid 16006: Queda de Árvore in Santo António da Serra (Santa Cruz), two corporations.
        $raw = $this->row(16006);
        $o = MadeiraOccurrence::where('key', ProcessMadeiraOccurrences::key($raw))->first();
        $this->assertSame($raw['codigo'], $o->natureCode);
        $this->assertSame($raw['codigocaop'], $o->parishCode);
        $this->assertSame(substr($raw['codigocaop'], 0, 4), $o->municipalityCode);
        $this->assertSame(MadeiraOccurrence::MUNICIPALITIES[$o->municipalityCode], $o->municipality);
        $this->assertSame(array_map('trim', explode(',', $raw['entidades'])), $o->entities);
        $this->assertSame($raw['inicio'], $o->start->getTimestampMs());

        // The raw content is archived, without the positional objectid.
        $payload = MadeiraOccurrenceRevision::where('key', $o->key)->first()->payload;
        $this->assertSame($raw['freguesia'], $payload['freguesia']);
        $this->assertArrayNotHasKey('objectid', $payload);

        $query = [];
        parse_str($this->history[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame('https://madeira.test/FeatureServer/0/query', (string) $this->history[0]['request']->getUri()->withQuery(''));
        $this->assertSame(['1=1', '*', '0', 'json'], [$query['where'], $query['outFields'], $query['resultOffset'], $query['f']]);
    }

    /** @test */
    public function shifted_objectids_are_not_changes(): void
    {
        // Seen in production: the view is synced by position, so a new row
        // moves every following row to another objectid.
        $rows = $this->rows();
        $this->job([$this->page($rows)])->handle();

        $shifted = $rows;
        foreach ($shifted as $i => $row) {
            $shifted[$i]['objectid'] = $row['objectid'] + 1;
            $shifted[$i]['atualizadoem'] = $row['atualizadoem'] + 30000; // re-synced
        }

        $job = $this->job([$this->page($shifted)]);
        $job->handle();

        $this->assertSame(0, $job->created + $job->changed + $job->updated + $job->removed);
        $this->assertSame(MadeiraOccurrence::count(), MadeiraOccurrenceRevision::count());
    }

    /** @test */
    public function identical_rows_are_one_content_with_copies(): void
    {
        $row = $this->rows()[0];
        $twin = ['objectid' => $row['objectid'] + 100000] + $row;

        $this->job([$this->page([$row, $twin])])->handle();

        $this->assertSame(1, MadeiraOccurrence::count());
        $this->assertSame(2, MadeiraOccurrence::first()->copies);

        $this->job([$this->page([$row])])->handle();
        $this->assertSame(1, MadeiraOccurrence::first()->copies);
        $this->assertSame(['added', 'copies'], MadeiraOccurrenceRevision::orderBy('_id')->pluck('change')->all());
    }

    /** @test */
    public function rows_without_parish_have_no_location(): void
    {
        $this->job([$this->page($this->rows())])->handle();

        $o = MadeiraOccurrence::whereNull('parishCode')->first();
        $this->assertNotNull($o);
        $this->assertNull($o->municipalityCode);
        $this->assertNull($o->municipality);
    }

    /** @test */
    public function pages_are_followed_until_the_transfer_limit_is_not_exceeded(): void
    {
        $rows = $this->rows();
        $this->job([
            $this->page(array_slice($rows, 0, 100), true),
            $this->page(array_slice($rows, 100)),
        ])->handle();

        $this->assertCount(2, $this->history);
        parse_str($this->history[1]['request']->getUri()->getQuery(), $query);
        $this->assertSame('2000', $query['resultOffset']);
        $this->assertSame(count($rows), MadeiraOccurrence::sum('copies'));
    }

    /** @test */
    public function an_edit_keeps_the_occurrence_and_logs_the_change(): void
    {
        $rows = $this->rows();
        $this->job([$this->page($rows)])->handle();
        $occurrenceId = MadeiraOccurrence::where('key', ProcessMadeiraOccurrences::key($rows[0]))->first()->occurrenceId;
        $published = MadeiraOccurrence::whereNull('removedAt')->count();

        $edited = $rows;
        $edited[0]['viaturas'] = 7;
        $edited[0]['bombeiros'] = 12;

        $job = $this->job([$this->page($rows), $this->page($edited)]);
        $job->handle(); // identical
        $this->assertSame(0, $job->created + $job->changed + $job->updated + $job->removed);
        $job->handle();

        $this->assertSame([0, 1, 0], [$job->created, $job->changed, $job->removed]);
        $this->assertSame($published, MadeiraOccurrence::whereNull('removedAt')->count());

        $old = MadeiraOccurrence::where('key', ProcessMadeiraOccurrences::key($rows[0]))->first();
        $new = MadeiraOccurrence::where('key', ProcessMadeiraOccurrences::key($edited[0]))->first();
        $this->assertNotNull($old->removedAt);
        $this->assertSame($new->key, $old->replacedBy);
        $this->assertNull($new->removedAt);
        $this->assertSame($old->key, $new->previousKey);
        $this->assertSame($occurrenceId, $new->occurrenceId);
        $this->assertSame(7, $new->vehicles);

        $log = MadeiraOccurrenceRevision::where('occurrenceId', $occurrenceId)->orderBy('_id')->get();
        $this->assertSame(['added', 'changed'], $log->pluck('change')->all());
        $this->assertSame([
            'firefighters' => [$rows[0]['bombeiros'], 12],
            'vehicles' => [$rows[0]['viaturas'], 7],
        ], $log[1]->changes);
        $this->assertSame(7, $log[1]->payload['viaturas']);
    }

    /** @test */
    public function a_reclassified_nature_is_linked_by_the_start_time(): void
    {
        $rows = $this->rows();
        $this->job([$this->page($rows)])->handle();
        $occurrenceId = MadeiraOccurrence::where('key', ProcessMadeiraOccurrences::key($rows[0]))->first()->occurrenceId;

        $edited = $rows;
        $edited[0]['codigo'] = '9999';
        $edited[0]['tipologiafonte'] = 'Reclassificada';
        $this->job([$this->page($edited)])->handle();

        $new = MadeiraOccurrence::where('key', ProcessMadeiraOccurrences::key($edited[0]))->first();
        $this->assertSame($occurrenceId, $new->occurrenceId);
        $this->assertSame(
            ['natureCode' => [$rows[0]['codigo'], '9999'], 'nature' => [$rows[0]['tipologiafonte'], 'Reclassificada']],
            MadeiraOccurrenceRevision::where('occurrenceId', $occurrenceId)->where('change', 'changed')->first()->changes
        );
    }

    /** @test */
    public function ambiguous_edits_are_not_linked(): void
    {
        // Two occurrences with the same start and nature, both edited in the
        // same run: no way to tell which became which.
        $a = $this->rows()[0];
        $b = ['objectid' => $a['objectid'] + 1, 'freguesia' => 'Outra', 'codigocaop' => '310101'] + $a;
        $this->job([$this->page([$a, $b])])->handle();

        $a2 = ['viaturas' => 9] + $a;
        $b2 = ['viaturas' => 9] + $b;
        $job = $this->job([$this->page([$a2, $b2])]);
        $job->handle();

        $this->assertSame([2, 0, 2], [$job->created, $job->changed, $job->removed]);
        $this->assertSame(4, MadeiraOccurrence::pluck('occurrenceId')->unique()->count());
    }

    /** @test */
    public function a_chain_of_edits_keeps_one_occurrence_id(): void
    {
        $row = $this->rows()[0];
        $versions = [$row, ['viaturas' => 3] + $row, ['viaturas' => 3, 'freguesia' => 'Monte', 'codigocaop' => '310302'] + $row];

        foreach ($versions as $v) {
            $this->job([$this->page([$v])])->handle();
        }

        $this->assertSame(1, MadeiraOccurrence::whereNull('removedAt')->count());
        $this->assertSame(1, MadeiraOccurrence::pluck('occurrenceId')->unique()->count());
        $this->assertSame(['added', 'changed', 'changed'], MadeiraOccurrenceRevision::orderBy('_id')->pluck('change')->all());
        $this->assertSame('Monte', MadeiraOccurrence::whereNull('removedAt')->first()->parish);
    }

    /** @test */
    public function a_row_no_longer_published_is_removed_and_can_come_back(): void
    {
        $rows = $this->rows();
        $gone = ProcessMadeiraOccurrences::key($rows[5]);
        $without = array_values(array_filter($rows, fn ($r) => ProcessMadeiraOccurrences::key($r) !== $gone));

        $this->job([$this->page($rows)])->handle();
        $job = $this->job([$this->page($without)]);
        $job->handle();

        $this->assertSame(1, $job->removed);
        $this->assertNotNull(MadeiraOccurrence::where('key', $gone)->first()->removedAt);

        $this->job([$this->page($rows)])->handle();
        $this->assertNull(MadeiraOccurrence::where('key', $gone)->first()->removedAt);
        $this->assertSame(['added', 'removed', 'restored'], MadeiraOccurrenceRevision::where('key', $gone)->orderBy('_id')->pluck('change')->all());
    }

    /** @test */
    public function bad_or_short_responses_remove_nothing(): void
    {
        $rows = $this->rows();
        $this->job([$this->page($rows)])->handle();
        $revisions = MadeiraOccurrenceRevision::count();

        $this->job([
            // second page fails: the whole run is dropped
            $this->page(array_slice($rows, 0, 10), true),
            new ConnectException('timeout', new Request('GET', 'https://madeira.test')),
        ])->handle();
        $this->job([new Response(200, [], json_encode(['error' => ['code' => 500, 'message' => 'oops']]))])->handle();
        $this->job([new Response(503, [], 'down')])->handle();
        $this->job([$this->page([])])->handle();
        $this->job([$this->page(array_slice($rows, 0, 10))])->handle(); // far fewer than stored: run skipped

        $this->assertSame(0, MadeiraOccurrence::whereNotNull('removedAt')->count());
        $this->assertSame($revisions, MadeiraOccurrenceRevision::count());
    }

    /** @test */
    public function nothing_is_fetched_without_the_url(): void
    {
        $this->setUrl(null);

        $this->job([$this->page($this->rows())])->handle();

        $this->assertCount(0, $this->history);
        $this->assertSame(0, MadeiraOccurrence::count());
    }
}

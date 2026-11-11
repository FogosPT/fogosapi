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
    public function the_table_is_stored_as_one_occurrence_per_row_with_a_revision(): void
    {
        $rows = $this->rows();
        $job = $this->job([$this->page($rows)]);
        $job->handle();

        $this->assertSame(count($rows), MadeiraOccurrence::count());
        $this->assertSame(count($rows), MadeiraOccurrenceRevision::count());
        $this->assertSame(count($rows), $job->created);

        // objectid 16006: Queda de Árvore in Santo António da Serra (Santa Cruz), two corporations.
        $o = MadeiraOccurrence::where('sourceId', 16006)->first();
        $raw = $this->row(16006);
        $this->assertSame($raw['codigo'], $o->natureCode);
        $this->assertSame($raw['codigocaop'], $o->parishCode);
        $this->assertSame(substr($raw['codigocaop'], 0, 4), $o->municipalityCode);
        $this->assertSame(MadeiraOccurrence::MUNICIPALITIES[$o->municipalityCode], $o->municipality);
        $this->assertSame(array_map('trim', explode(',', $raw['entidades'])), $o->entities);
        $this->assertSame($raw['inicio'], $o->start->getTimestampMs());

        $query = [];
        parse_str($this->history[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame('https://madeira.test/FeatureServer/0/query', (string) $this->history[0]['request']->getUri()->withQuery(''));
        $this->assertSame(['1=1', '*', '0', 'json'], [$query['where'], $query['outFields'], $query['resultOffset'], $query['f']]);
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
        $this->assertSame(count($rows), MadeiraOccurrence::count());
    }

    /** @test */
    public function unchanged_rows_add_no_revision_and_changed_ones_do(): void
    {
        $rows = $this->rows();
        $this->job([$this->page($rows)])->handle();

        $changed = $rows;
        $changed[0]['viaturas'] = 7;
        $changed[0]['atualizadoem'] += 60000;

        $job = $this->job([$this->page($rows), $this->page($changed)]);
        $job->handle(); // identical
        $this->assertSame(0, $job->created + $job->updated);
        $job->handle();

        $this->assertSame(1, $job->updated);
        $this->assertSame(count($rows) + 1, MadeiraOccurrenceRevision::count());
        $id = $rows[0]['objectid'];
        $this->assertSame(7, MadeiraOccurrence::where('sourceId', $id)->first()->vehicles);
        $this->assertSame([$rows[0]['viaturas'], 7], MadeiraOccurrenceRevision::where('sourceId', $id)->orderBy('_id')->get()->pluck('payload.viaturas')->all());
    }

    /** @test */
    public function a_row_no_longer_published_is_removed_and_can_come_back(): void
    {
        $rows = $this->rows();
        $gone = $rows[5]['objectid'];
        $without = array_values(array_filter($rows, fn ($r) => $r['objectid'] !== $gone));

        $this->job([$this->page($rows)])->handle();
        $job = $this->job([$this->page($without)]);
        $job->handle();

        $this->assertSame(1, $job->removed);
        $this->assertNotNull(MadeiraOccurrence::where('sourceId', $gone)->first()->removedAt);
        $this->assertTrue(MadeiraOccurrenceRevision::where('sourceId', $gone)->orderBy('_id', 'desc')->first()->removed);

        $this->job([$this->page($rows)])->handle();
        $this->assertNull(MadeiraOccurrence::where('sourceId', $gone)->first()->removedAt);
        $this->assertSame(3, MadeiraOccurrenceRevision::where('sourceId', $gone)->count());
    }

    /** @test */
    public function bad_or_short_responses_remove_nothing(): void
    {
        $rows = $this->rows();
        $this->job([$this->page($rows)])->handle();

        $this->job([
            // second page fails: the whole run is dropped
            $this->page(array_slice($rows, 0, 10), true),
            new ConnectException('timeout', new Request('GET', 'https://madeira.test')),
        ])->handle();
        $this->job([new Response(200, [], json_encode(['error' => ['code' => 500, 'message' => 'oops']]))])->handle();
        $this->job([new Response(503, [], 'down')])->handle();
        $this->job([$this->page([])])->handle();
        $this->job([$this->page(array_slice($rows, 0, 10))])->handle(); // far fewer than stored

        $this->assertSame(0, MadeiraOccurrence::whereNotNull('removedAt')->count());
        $this->assertSame(count($rows), MadeiraOccurrenceRevision::count());
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

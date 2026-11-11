<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessMadeiraEvents;
use App\Models\MadeiraEvent;
use App\Models\MadeiraEventSnapshot;
use App\Models\MadeiraFeedRevision;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ProcessMadeiraEventsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUrl('https://madeira.test/dados');
    }

    protected function tearDown(): void
    {
        $this->setUrl(null);
        parent::tearDown();
    }

    /**
     * env() reads $_SERVER/$_ENV before getenv(), and Dotenv fills them from
     * .env (CI copies .env.example, which has MADEIRA_EVENTS_URL empty).
     */
    private function setUrl(?string $url): void
    {
        if ($url === null) {
            unset($_SERVER['MADEIRA_EVENTS_URL'], $_ENV['MADEIRA_EVENTS_URL']);
            putenv('MADEIRA_EVENTS_URL');

            return;
        }

        $_SERVER['MADEIRA_EVENTS_URL'] = $_ENV['MADEIRA_EVENTS_URL'] = $url;
        putenv('MADEIRA_EVENTS_URL='.$url);
    }

    private function feed(array $overrides = []): array
    {
        $feed = json_decode(file_get_contents(base_path('tests/fixtures/madeira/dados.json')), true);

        return array_replace_recursive($feed, $overrides);
    }

    private function job(array $responses): ProcessMadeiraEvents
    {
        return (new ProcessMadeiraEvents())->withClient(
            new Client(['http_errors' => false, 'handler' => HandlerStack::create(new MockHandler($responses))])
        );
    }

    /** @test */
    public function the_feed_is_stored_as_one_event_with_its_aggregates(): void
    {
        $this->job([new Response(200, [], json_encode($this->feed()))])->handle();

        $this->assertSame(1, MadeiraEvent::count());
        $event = MadeiraEvent::first();

        $this->assertSame(md5('Depressão Charles – Precipitação e vento forte|2026-11-10T20:00'), $event->eventId);
        $this->assertSame('ATIVO', $event->status);
        $this->assertTrue($event->active);
        $this->assertSame('Madeira e Porto Santo', $event->zone);
        // Madeira local time is UTC in winter.
        $this->assertSame('2026-11-10T20:00:00Z', $event->start->utc()->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame('2026-11-11T09:25:00Z', $event->sourceUpdatedAt->utc()->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame(212, $event->kpi['occurrences']);
        $this->assertSame(18, $event->kpi['activeOccurrences']);
        $this->assertSame(553, $event->kpi['operationals']);
        $this->assertSame(['time' => '2026-11-11T06:00:00Z', 'occurrences' => 36], $event->kpi['peak']);
        $this->assertCount(12, $event->municipalities);

        $funchal = collect($event->municipalities)->firstWhere('name', 'Funchal');
        $this->assertSame(42, $funchal['occurrences']);
        $this->assertSame(107, $funchal['operationals']);
        $this->assertSame(
            ['code' => '3315', 'description' => 'Inundação de estruturas ou superfícies por precipitação intensa', 'occurrences' => 16],
            $funchal['natures'][0]
        );
        $this->assertCount(11, $event->natures);
        $this->assertCount(14, $event->hourly);
        $this->assertSame(['time' => '2026-11-11T09:00:00Z', 'occurrences' => 4, 'cumulative' => 212], $event->hourly[13]);

        $this->assertSame(1, MadeiraEventSnapshot::count());
        $this->assertSame(18, MadeiraEventSnapshot::first()->activeOccurrences);
    }

    /** @test */
    public function each_new_update_adds_a_snapshot_and_a_repeated_one_does_not(): void
    {
        $job = new ProcessMadeiraEvents();
        $job->sync($this->feed());
        $job->sync($this->feed());
        $job->sync($this->feed(['atualizado' => '2026-11-11T09:30', 'kpi' => ['ativas' => 12, 'ocorrencias' => 215]]));

        $this->assertSame(1, MadeiraEvent::count());
        $this->assertSame(12, MadeiraEvent::first()->kpi['activeOccurrences']);

        $series = MadeiraEventSnapshot::orderBy('time')->get();
        $this->assertCount(2, $series);
        $this->assertSame([18, 12], $series->pluck('activeOccurrences')->all());
        $this->assertSame([212, 215], $series->pluck('occurrences')->all());
    }

    /** @test */
    public function road_restrictions_and_communiques_are_stored(): void
    {
        $official = $this->feed()['oficiais'][0];

        (new ProcessMadeiraEvents())->sync($this->feed(['evento' => [
            'condicionamentos' => [
                ['via' => 'ER101 – São Vicente', 'nivel' => 'cortada', 'texto' => 'Derrocada ao km 12.', 'data' => '2026-11-11T08:40'],
                ['via' => 'VR1 – Machico', 'nivel' => 'condicionada', 'texto' => 'Uma via aberta.'],
            ],
            'comunicados' => [
                ['titulo' => 'Ponto de situação 08h00', 'data' => '2026-11-11T08:05', 'texto' => 'Balanço da noite.'],
                // Also in `oficiais`: kept once, as official.
                ['titulo' => $official['titulo'], 'data' => $official['data'], 'link' => $official['link']],
            ],
        ]]));

        $event = MadeiraEvent::first();

        $this->assertSame([
            ['road' => 'ER101 – São Vicente', 'closed' => true, 'text' => 'Derrocada ao km 12.', 'time' => '2026-11-11T08:40:00Z'],
            ['road' => 'VR1 – Machico', 'closed' => false, 'text' => 'Uma via aberta.', 'time' => null],
        ], $event->roadRestrictions);

        // 5 official + 1 event-only, newest first.
        $this->assertCount(6, $event->communiques);
        $this->assertSame(
            ['title' => 'Ponto de situação 08h00', 'time' => '2026-11-11T08:05:00Z', 'text' => 'Balanço da noite.', 'link' => null, 'official' => false],
            $event->communiques[0]
        );
        $this->assertSame($official['titulo'], $event->communiques[1]['title']);
        $this->assertTrue($event->communiques[1]['official']);
        $this->assertSame('2026-11-10T15:27:00Z', $event->communiques[1]['time']);
        $this->assertSame($official['link'], $event->communiques[1]['link']);
    }

    /** @test */
    public function every_distinct_version_of_the_feed_is_archived_raw(): void
    {
        $job = new ProcessMadeiraEvents();
        $job->sync($this->feed());
        $job->sync($this->feed()); // repeated
        $job->sync($this->feed(['sincronizado' => '2026-11-11T09:26'])); // only the poll time changed
        // Same `atualizado`, but a road was closed in the meantime.
        $job->sync($this->feed(['evento' => ['condicionamentos' => [
            ['via' => 'ER101', 'nivel' => 'cortada', 'texto' => 'Derrocada.'],
        ]]]));
        $job->sync($this->feed(['atualizado' => '2026-11-11T09:30', 'kpi' => ['ativas' => 12]]));

        $revisions = MadeiraFeedRevision::orderBy('_id')->get();
        $this->assertCount(3, $revisions);

        $eventId = md5('Depressão Charles – Precipitação e vento forte|2026-11-10T20:00');
        $this->assertSame([$eventId, $eventId, $eventId], $revisions->pluck('eventId')->all());

        // Raw, including what the API does not serve (IPMA warnings, forecast).
        $first = $revisions[0]->payload;
        $this->assertSame(18, $first['kpi']['ativas']);
        $this->assertSame('Vento', $first['avisos']['avisos'][1]['tipo']);
        $this->assertSame('ECMWF IFS', $first['meteo']['modelo']);
        $this->assertSame('ER101', $revisions[1]->payload['evento']['condicionamentos'][0]['via']);
        $this->assertSame(12, $revisions[2]->payload['kpi']['ativas']);
        $this->assertSame('2026-11-11T09:30:00Z', $revisions[2]->sourceUpdatedAt->utc()->format('Y-m-d\TH:i:s\Z'));

        // The endpoints' view is the current version.
        $this->assertSame(12, MadeiraEvent::first()->kpi['activeOccurrences']);
        $this->assertSame([], MadeiraEvent::first()->roadRestrictions);
    }

    /** @test */
    public function a_feed_without_an_event_is_archived_too(): void
    {
        (new ProcessMadeiraEvents())->sync(['evento' => null, 'atualizado' => '2026-11-12T10:00', 'kpi' => []]);

        $this->assertSame(0, MadeiraEvent::count());
        $this->assertSame(1, MadeiraFeedRevision::count());
        $this->assertNull(MadeiraFeedRevision::first()->eventId);
    }

    /** @test */
    public function an_older_feed_is_ignored(): void
    {
        $job = new ProcessMadeiraEvents();
        $job->sync($this->feed());
        $job->sync($this->feed(['atualizado' => '2026-11-11T09:00', 'kpi' => ['ativas' => 30]]));

        $this->assertSame(18, MadeiraEvent::first()->kpi['activeOccurrences']);
        $this->assertSame(1, MadeiraEventSnapshot::count());
    }

    /** @test */
    public function a_new_event_closes_the_previous_one(): void
    {
        $job = new ProcessMadeiraEvents();
        $job->sync($this->feed());
        $job->sync($this->feed([
            'evento' => ['titulo' => 'Tempo adverso – Precipitação intensa'],
            'periodo' => ['inicio' => '2026-11-14T08:00'],
            'atualizado' => '2026-11-14T09:00',
        ]));

        $this->assertSame(2, MadeiraEvent::count());
        $this->assertSame(['Tempo adverso – Precipitação intensa'], MadeiraEvent::where('active', true)->pluck('title')->all());
    }

    /** @test */
    public function an_ended_event_is_no_longer_active(): void
    {
        $job = new ProcessMadeiraEvents();
        $job->sync($this->feed());
        $job->sync($this->feed(['evento' => ['estado' => 'ENCERRADO'], 'atualizado' => '2026-11-11T12:00']));

        $this->assertFalse(MadeiraEvent::first()->active);
        $this->assertSame('ENCERRADO', MadeiraEvent::first()->status);
    }

    /** @test */
    public function failures_and_payloads_without_an_event_store_nothing(): void
    {
        $this->job([
            new ConnectException('timeout', new Request('GET', 'https://madeira.test/dados')),
        ])->handle();
        $this->job([new Response(500, [], 'oops')])->handle();
        $this->job([new Response(200, [], '<html>')])->handle();
        $this->job([new Response(200, [], json_encode(['evento' => null, 'kpi' => []]))])->handle();

        $this->assertSame(0, MadeiraEvent::count());
        $this->assertSame(0, MadeiraEventSnapshot::count());
    }

    /** @test */
    public function nothing_is_fetched_without_the_url(): void
    {
        $this->setUrl(null);

        $mock = new MockHandler([new Response(200, [], json_encode($this->feed()))]);
        (new ProcessMadeiraEvents())->withClient(new Client(['handler' => HandlerStack::create($mock)]))->handle();

        $this->assertSame(1, $mock->count());
        $this->assertSame(0, MadeiraEvent::count());
    }
}

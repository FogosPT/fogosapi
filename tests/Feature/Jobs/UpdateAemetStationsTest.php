<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessAemetWarnings;
use App\Jobs\UpdateAemetDataDaily;
use App\Jobs\UpdateAemetObservations;
use App\Jobs\UpdateAemetStations;
use App\Models\AemetDataDaily;
use App\Models\AemetObservation;
use App\Models\AemetStation;
use App\Support\AemetFeedHealth;
use App\Tools\AemetTool;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Stations, hourly observations and daily summaries of the AEMET (Spain) stations,
 * fixtures tests/fixtures/aemet/{inventario,observacion,diarios}.json (ISO-8859-15).
 */
class UpdateAemetStationsTest extends TestCase
{
    use DatabaseMigrations;

    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->history = [];
        config(['services.aemet.key' => 'test-key', 'services.aemet.stale_minutes' => 60]);
        Cache::flush();
        Carbon::setTestNow('2026-11-13T12:00:00Z');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tool(array $responses): AemetTool
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new AemetTool(new Client(['http_errors' => false, 'handler' => $stack]));
    }

    /** The two-step responses serving the fixture `$name`. */
    private function serving(string $name): array
    {
        return [
            new Response(200, [], json_encode([
                'descripcion' => 'exito',
                'estado' => 200,
                'datos' => 'https://opendata.aemet.es/opendata/sh/'.$name,
                'metadatos' => 'https://opendata.aemet.es/opendata/sh/meta',
            ])),
            new Response(200, [], file_get_contents(base_path('tests/fixtures/aemet/'.$name))),
        ];
    }

    private function stations(): UpdateAemetStations
    {
        $job = (new UpdateAemetStations())->withTool($this->tool($this->serving('inventario.json')));
        $job->handle();

        return $job;
    }

    private function observations(?array $responses = null): UpdateAemetObservations
    {
        $job = (new UpdateAemetObservations())->withTool($this->tool($responses ?? $this->serving('observacion.json')));
        $job->handle();

        return $job;
    }

    private function daily(?array $responses = null): UpdateAemetDataDaily
    {
        $job = (new UpdateAemetDataDaily())->withTool($this->tool($responses ?? $this->serving('diarios.json')));
        $job->handle();

        return $job;
    }

    /** @test */
    public function jobs_are_unique_and_time_out_below_retry_after(): void
    {
        foreach ([new UpdateAemetStations(), new UpdateAemetObservations(), new UpdateAemetDataDaily()] as $job) {
            $this->assertInstanceOf(ShouldBeUnique::class, $job);
            $this->assertLessThan(90, $job->timeout);
        }
    }

    /** @test */
    public function jobs_run_on_their_own_queue(): void
    {
        Queue::fake();

        $jobs = [ProcessAemetWarnings::class, UpdateAemetObservations::class, UpdateAemetStations::class, UpdateAemetDataDaily::class];
        foreach ($jobs as $class) {
            $this->assertSame('aemet', (new $class())->queue, $class);
            dispatch(new $class());
            Queue::assertPushedOn('aemet', $class);
        }
    }

    /** @test */
    public function unauthorized_is_announced_at_once(): void
    {
        $unauthorized = fn () => (new UpdateAemetStations())->withTool($this->tool([new Response(401, [], '')]));

        $job = $unauthorized();
        $job->handle();
        $this->assertNotNull($job->failureReason);
        $this->assertStringStartsWith('AEMET: chave AEMET inválida ou expirada', $job->unauthorizedMessage);

        Carbon::setTestNow('2026-11-13T12:15:00Z');
        $again = $unauthorized();
        $again->handle();
        $this->assertNull($again->unauthorizedMessage, 'announced once');

        // A 401 on another source is the same key: not announced again.
        $observations = $this->observations([new Response(401, [], '')]);
        $this->assertNull($observations->unauthorizedMessage);

        // Any success clears it; a new 401 is announced again.
        $this->stations();
        $daily = $this->daily([new Response(401, [], '')]);
        $this->assertStringStartsWith('AEMET: chave AEMET inválida ou expirada', $daily->unauthorizedMessage);
    }

    /** @test */
    public function stations_from_inventory(): void
    {
        $job = $this->stations();

        $this->assertNull($job->failureReason);
        $this->assertSame(3, AemetStation::count());

        $caceres = AemetStation::where('stationId', '3469A')->first();
        $this->assertSame('CÁCERES', $caceres->location);
        $this->assertSame('CACERES', $caceres->place);
        $this->assertSame([-6.3389, 39.4714], $caceres->coordinates); // 062020W, 392817N
        $this->assertSame(394.0, $caceres->altitude);
        $this->assertTrue($caceres->peninsula);
        $this->assertNotNull($caceres->lastSeenAt);

        $lluc = AemetStation::where('stationId', 'B013X')->first();
        $this->assertSame([2.8858, 39.8233], $lluc->coordinates);
        $this->assertFalse($lluc->peninsula);

        $this->assertSame('VANDELLÒS', AemetStation::where('stationId', '0002I')->first()->location);

        $this->stations();
        $this->assertSame(3, AemetStation::count());
    }

    /** @test */
    public function observations_upsert_and_are_idempotent(): void
    {
        $this->stations();
        $job = $this->observations();

        $this->assertNull($job->failureReason);
        $count = AemetObservation::count();
        $this->assertSame(37, $count); // one per (idema, fint) in the fixture

        $this->observations();
        $this->assertSame($count, AemetObservation::count());

        $obs = AemetObservation::where('stationId', '3469A')
            ->where('date', Carbon::parse('2026-11-12T05:00:00Z'))->first();
        $this->assertNotNull($obs);
        $this->assertSame(14.2, $obs->temperatura); // ta
        $this->assertSame(11.5, $obs->intensidadeVentoKM);
        $this->assertSame(6, $obs->idDireccVento);
        $this->assertSame('CÁCERES', $obs->raw['ubi']);
        $this->assertNull($obs->radiacao);
        $this->assertSame('2026-11-12T05:00:00Z', Carbon::parse($obs->date)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'));

        // Inventory data is not overwritten by the observations.
        $this->assertSame('CACERES', AemetStation::where('stationId', '3469A')->first()->place);
        $this->assertFalse(AemetFeedHealth::isStale('observations'));
    }

    /** @test */
    public function observation_of_unknown_station_creates_it(): void
    {
        $this->stations();
        $this->observations();

        $alforja = AemetStation::where('stationId', '0009X')->first();
        $this->assertNotNull($alforja);
        $this->assertSame('ALFORJA', $alforja->location);
        $this->assertSame([0.963335, 41.213892], $alforja->coordinates);
        $this->assertSame(406.0, $alforja->altitude);
        $this->assertTrue($alforja->peninsula);
        $this->assertNull($alforja->place);
        $this->assertSame(4, AemetStation::count());
    }

    /** @test */
    public function daily_requests_last_10_days(): void
    {
        $job = $this->daily();

        $this->assertNull($job->failureReason);
        $from = Carbon::now('Europe/Madrid')->startOfDay()->subDays(10)->format('Y-m-d');
        $to = Carbon::now('Europe/Madrid')->format('Y-m-d');
        $this->assertSame('2026-11-03', $from);
        $this->assertSame(
            "https://opendata.aemet.es/opendata/api/valores/climatologicos/diarios/datos/fechaini/{$from}T00:00:00UTC/fechafin/{$to}T23:59:59UTC/todasestaciones",
            (string) $this->history[0]['request']->getUri()
        );
    }

    /** @test */
    public function daily_upsert_by_station_and_civil_day(): void
    {
        $this->daily();
        $this->assertSame(5, AemetDataDaily::count());
        $this->daily();
        $this->assertSame(5, AemetDataDaily::count());

        $row = AemetDataDaily::where('stationId', '3469A')->first();
        // `date` is the civil day as UTC midnight.
        $this->assertSame('2026-11-07T00:00:00Z', Carbon::parse($row->date)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame(22.9, $row->temp_max);
        $this->assertSame(4.0, $row->vento_int_med);
        $this->assertNull($row->vento_dir_max);
        $this->assertSame('Varias', $row->raw['horaHrMin']);
        $this->assertFalse(AemetFeedHealth::isStale('daily'));
    }

    /** @test */
    public function failures_feed_health(): void
    {
        $this->observations(); // success at 12:00

        $job = $this->observations([new Response(500)]);
        $this->assertNotNull($job->failureReason);
        $this->assertFalse(AemetFeedHealth::isStale('observations'));

        Carbon::setTestNow('2026-11-13T13:01:00Z');
        $job = $this->observations([new Response(500)]);
        $this->assertNotNull($job->failureReason);
        $this->assertTrue(AemetFeedHealth::isStale('observations'));
        $this->assertNotNull($job->healthMessage);

        // The daily source has a 48 h window.
        $this->assertNotNull($this->daily([new Response(500)])->failureReason);
        Carbon::setTestNow('2026-11-15T13:02:00Z'); // 48 h + 1 min after the first failure
        $this->daily([new Response(500)]);
        $this->assertTrue(AemetFeedHealth::isStale('daily'));
    }

    /** @test */
    public function an_empty_feed_is_a_failure(): void
    {
        $job = $this->observations([
            new Response(200, [], json_encode(['descripcion' => 'exito', 'estado' => 200, 'datos' => 'https://x/y'])),
            new Response(200, [], '[]'),
        ]);

        $this->assertNotNull($job->failureReason);
        $this->assertSame(0, AemetObservation::count());
    }

    /** @test */
    public function no_key_does_nothing(): void
    {
        config(['services.aemet.key' => '']);

        $job = (new UpdateAemetObservations())->withTool($this->tool([]));
        $job->handle();

        $this->assertNull($job->failureReason);
        $this->assertCount(0, $this->history);
    }

    /** @test */
    public function commands_fail_when_the_import_fails(): void
    {
        $this->app->instance(AemetTool::class, $this->tool([new Response(500), new Response(500), new Response(500)]));

        $this->artisan('aemet:stations')->assertExitCode(1);
        $this->artisan('aemet:observations')->assertExitCode(1);
        $this->artisan('aemet:daily')->assertExitCode(1);
    }

    /** @test */
    public function commands_import(): void
    {
        $this->app->instance(AemetTool::class, $this->tool(array_merge(
            $this->serving('inventario.json'),
            $this->serving('observacion.json'),
            $this->serving('diarios.json'),
        )));

        $this->artisan('aemet:stations')->assertExitCode(0);
        $this->artisan('aemet:observations')->assertExitCode(0);
        $this->artisan('aemet:daily')->assertExitCode(0);
        $this->assertSame(4, AemetStation::count());
    }
}

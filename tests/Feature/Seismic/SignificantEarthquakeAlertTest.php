<?php

namespace Tests\Feature\Seismic;

use App\Jobs\HandleSignificantEarthquake;
use App\Jobs\ProcessSeismicSource;
use App\Models\Earthquake;
use App\Support\EarthquakeAlert;
use App\Support\EarthquakeMerger;
use App\Tools\DiscordTool;
use App\Tools\Seismic\IpmaSeismicTool;
use App\Tools\Seismic\SeismicEvent;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SignificantEarthquakeAlertTest extends TestCase
{
    use DatabaseMigrations;

    private EarthquakeMerger $merger;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-28T15:30:00Z');
        $this->merger = new EarthquakeMerger();
        $this->setEnv('SEISMIC_NOTIFICATIONS_ENABLE', 'true');
        DiscordTool::$sent = [];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->setEnv('SEISMIC_NOTIFICATIONS_ENABLE', 'false');
        DiscordTool::$sent = null;
        parent::tearDown();
    }

    private function setEnv(string $key, string $value): void
    {
        $_SERVER[$key] = $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }

    private function quake(float $m, array $overrides = []): Earthquake
    {
        $o = $overrides + ['time' => '2026-10-28T15:18:14Z', 'lat' => 37.842, 'lon' => -26.247, 'source' => 'ipma', 'id' => 'q1', 'felt' => null, 'intensity' => null, 'local' => null];
        [$q] = $this->merger->upsert(new SeismicEvent($o['source'], $o['id'], Carbon::parse($o['time']), $o['lat'], $o['lon'], 10.0, $m, 'ML', 'Fossa de Hirondelle', $o['felt'], $o['intensity'], $o['local']));

        return $q;
    }

    private function runAlert(Earthquake $q): void
    {
        (new HandleSignificantEarthquake($q->id))->handle();
    }

    /** @test */
    public function qualifying_rules(): void
    {
        $this->assertTrue(EarthquakeAlert::qualifies($this->quake(3.0)));
        $this->assertFalse(EarthquakeAlert::qualifies($this->quake(2.9, ['id' => 'q2', 'time' => '2026-10-28T12:00:00Z'])));
        // Too old (backfill / late catalogue additions).
        $this->assertFalse(EarthquakeAlert::qualifies($this->quake(4.5, ['id' => 'q3', 'time' => '2026-10-27T12:00:00Z'])));
        // Outside Portugal: only when felt.
        $this->assertFalse(EarthquakeAlert::qualifies($this->quake(3.5, ['id' => 'q4', 'lat' => 42.46, 'lon' => -6.6])));
        $this->assertTrue(EarthquakeAlert::qualifies($this->quake(3.5, ['id' => 'q5', 'lat' => 42.46, 'lon' => -6.6, 'time' => '2026-10-28T13:00:00Z', 'felt' => true])));
    }

    /** @test */
    public function a_significant_earthquake_is_posted_once(): void
    {
        $q = $this->quake(3.9);

        $this->runAlert($q);
        $this->runAlert($q);

        $this->assertCount(1, DiscordTool::$sent);
        $msg = DiscordTool::$sent[0];
        $this->assertStringContainsString('Sismo de magnitude 3,9 (ML)', $msg);
        $this->assertStringContainsString('Fossa de Hirondelle, Açores', $msg);
        $this->assertStringContainsString('28/10 14:18 hora dos Açores', $msg);
        $this->assertStringContainsString('https://sismos.pt/sismo/'.$q->id.'-'.$q->fresh()->slug, $msg);
        $this->assertNotNull($q->fresh()->notifiedAt);
    }

    /** @test */
    public function revisions_are_posted_when_magnitude_moves_or_it_becomes_felt(): void
    {
        $q = $this->quake(3.9);
        $this->runAlert($q);

        // Small revision: nothing.
        $q = $this->quake(4.0);
        $this->runAlert($q);
        $this->assertCount(1, DiscordTool::$sent);

        // ≥ 0.3: revision.
        $q = $this->quake(4.3);
        $this->runAlert($q);
        $this->assertCount(2, DiscordTool::$sent);
        $this->assertStringContainsString('Magnitude revista: 3,9 → 4,3', DiscordTool::$sent[1]);

        // IPMA adds the macroseismic report.
        $q = $this->quake(4.3, ['felt' => true, 'intensity' => 'IV', 'local' => 'Ponta Delgada']);
        $this->runAlert($q);
        $this->assertCount(3, DiscordTool::$sent);
        $this->assertStringContainsString('Sentido com intensidade máxima IV em Ponta Delgada.', DiscordTool::$sent[2]);

        $this->runAlert($q);
        $this->assertCount(3, DiscordTool::$sent);
    }

    /** @test */
    public function nothing_is_posted_when_notifications_are_disabled(): void
    {
        $this->setEnv('SEISMIC_NOTIFICATIONS_ENABLE', 'false');
        $q = $this->quake(4.5);
        $this->runAlert($q);

        $this->assertSame([], DiscordTool::$sent);
        $this->assertNull($q->fresh()->notifiedAt);
    }

    /** @test */
    public function polling_dispatches_the_alert_job_only_for_qualifying_events(): void
    {
        Queue::fake();

        $fixture = json_encode(['idArea' => 3, 'updateDate' => '2026-10-28T15:25:00', 'data' => [
            ['time' => '2026-10-28T15:18:14', 'lat' => '37.8420', 'lon' => '-26.2470', 'depth' => 10, 'magnitud' => '3.9', 'magType' => 'L', 'obsRegion' => 'Fossa de Hirondelle', 'sismoId' => 'A1'],
            ['time' => '2026-10-28T14:00:00', 'lat' => '37.8420', 'lon' => '-26.2470', 'depth' => 10, 'magnitud' => '2.1', 'magType' => 'L', 'obsRegion' => 'Fossa de Hirondelle', 'sismoId' => 'A2'],
        ]]);
        $tool = new IpmaSeismicTool(new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], $fixture),
            new Response(200, [], '{"idArea": 7, "data": []}'),
        ]))]));

        (new ProcessSeismicSource('ipma'))->withTool($tool)->handle();

        Queue::assertPushed(HandleSignificantEarthquake::class, 1);
        Queue::assertPushed(HandleSignificantEarthquake::class, fn ($job) => $job->earthquakeId === Earthquake::where('magnitude', 3.9)->first()->id);
    }
}

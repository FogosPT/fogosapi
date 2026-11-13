<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessAemetWarnings;
use App\Jobs\SendWeatherWarningToTelegram;
use App\Models\AemetWarning;
use App\Models\AemetWarningEvent;
use App\Models\AemetWarningMessage;
use App\Models\AemetWarningZone;
use App\Models\WeatherWarning;
use App\Models\WeatherWarningEvent;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Fixture tests/fixtures/aemet/avisos.tar — at the test clock (2026-11-12T16:00Z) the
 * warnings in force (endTime ≥ now, not verde) are 713201 (yellow, starts 17:00Z),
 * 713603 (yellow, border zone; 3 messages merged) and 645405C (orange, Baleares coastal).
 * 774601 (until 13:59Z) and 622203 (until 10:59Z) have expired; 71VV71 is verde.
 * The fixture has no `713603C` zone: the coastal/non-peninsula zone checked is 645405C.
 */
class ProcessAemetWarningsTest extends TestCase
{
    use DatabaseMigrations;

    private const ZONE_713603 = 'Z_CAP_C_LEMM_20261112092714_AFAZ713603PRP11223.xml';

    private const VERDE = 'Z_CAP_C_LEMM_20261111225001_AFAZ71VV71PRP11423.xml';

    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->history = [];
        config(['services.aemet.key' => 'test-key', 'services.aemet.stale_minutes' => 60]);
        Cache::flush();
        Carbon::setTestNow('2026-11-12T16:00:00Z');
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

    private function job(array $responses): ProcessAemetWarnings
    {
        return (new ProcessAemetWarnings())->withTool($this->tool($responses));
    }

    /** The two-step responses serving `$tar`. */
    private function serving(string $tar): array
    {
        return [
            new Response(200, [], json_encode([
                'descripcion' => 'exito',
                'estado' => 200,
                'datos' => 'https://opendata.aemet.es/opendata/sh/tar',
                'metadatos' => 'https://opendata.aemet.es/opendata/sh/meta',
            ])),
            new Response(200, ['Content-Type' => 'application/x-tar'], $tar),
        ];
    }

    private function runJob(?string $tar = null): ProcessAemetWarnings
    {
        $job = $this->job($this->serving($tar ?? $this->fixture()));
        $job->handle();

        return $job;
    }

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/fixtures/aemet/avisos.tar'));
    }

    /** @return array<string, string> file name → contents */
    private function entries(string $tar): array
    {
        $files = [];
        for ($offset = 0; $offset + 512 <= strlen($tar); ) {
            $header = substr($tar, $offset, 512);
            if (trim($header, "\0") === '') {
                break;
            }
            $name = rtrim(substr($header, 0, 100), "\0");
            $size = octdec(trim(substr($header, 124, 12), "\0 "));
            $type = $header[156];
            $offset += 512;
            if ($type === '0' || $type === "\0") {
                $files[$name] = substr($tar, $offset, $size);
            }
            $offset += (int) ceil($size / 512) * 512;
        }

        return $files;
    }

    /** @param array<string, string> $files */
    private function tar(array $files): string
    {
        $out = '';
        foreach ($files as $name => $content) {
            $header = str_pad($name, 100, "\0")
                .str_pad('0000644', 7, '0', STR_PAD_LEFT)."\0"
                .str_pad('0', 7, '0', STR_PAD_LEFT)."\0"
                .str_pad('0', 7, '0', STR_PAD_LEFT)."\0"
                .str_pad(decoct(strlen($content)), 11, '0', STR_PAD_LEFT)."\0"
                .str_pad(decoct(1762963200), 11, '0', STR_PAD_LEFT)."\0"
                .'        '
                .'0'
                .str_repeat("\0", 100)
                ."ustar\0".'00'
                .str_repeat("\0", 247);
            $sum = 0;
            for ($i = 0; $i < 512; ++$i) {
                $sum += ord($header[$i]);
            }
            $header = substr_replace($header, str_pad(decoct($sum), 6, '0', STR_PAD_LEFT)."\0 ", 148, 8);
            $out .= $header.str_pad($content, (int) ceil(strlen($content) / 512) * 512, "\0");
        }

        return $out.str_repeat("\0", 1024);
    }

    /** @return string[] */
    private function activeZones(): array
    {
        return AemetWarning::whereNull('supersededAt')->where('endTime', '>=', Carbon::now())
            ->pluck('district')->unique()->sort()->values()->all();
    }

    /** @test */
    public function imports_warnings_zones_and_messages(): void
    {
        $job = $this->runJob();

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame(80, $job->timeout);
        $this->assertSame(900, $job->uniqueFor);

        $this->assertSame(['645405C', '713201', '713603'], $this->activeZones());
        $this->assertSame(0, AemetWarning::where('district', '622203')->count());
        $this->assertSame(0, WeatherWarning::count(), 'nothing written to the IPMA collection');
        $this->assertSame(0, WeatherWarningEvent::count(), 'nothing written to the IPMA event log');
        $this->assertGreaterThan(0, AemetWarningEvent::where('kind', 'emitido')->count());

        $border = AemetWarning::where('district', '713603')->first();
        $this->assertSame('yellow', $border->level);
        $this->assertSame('Precipitação', $border->type);
        $this->assertSame('PR', $border->phenomenon);
        $this->assertSame('Miño de Pontevedra', $border->areaName);
        $this->assertIsArray($border->identifiers);
        $this->assertContains('2.49.0.0.724.0.ES.20261112092714.713603PRP112231794475634', $border->identifiers);
        $this->assertIsArray($border->en);

        $this->assertSame(9, AemetWarningMessage::count());
        $message = AemetWarningMessage::where('identifier', '2.49.0.0.724.0.ES.20261112092714.713603PRP112231794475634')->first();
        $this->assertSame('Update', $message->msgType);
        $this->assertSame('amarillo', $message->level);
        $this->assertSame(['713603'], $message->zones);
        $this->assertStringContainsString('<alert', $message->raw);
        $this->assertSame('2026-11-12T22:59:59+00:00', $message->expires->toIso8601String());

        $coastal = AemetWarningZone::where('code', '645405C')->first();
        $this->assertNotNull($coastal);
        $this->assertTrue($coastal->coastal);
        $this->assertFalse($coastal->peninsula);
        $this->assertSame('64', $coastal->ccaa);
        $this->assertSame('54', $coastal->province);
        $this->assertSame('MultiPolygon', $coastal->geometry['type']);

        $inland = AemetWarningZone::where('code', '713603')->first();
        $this->assertFalse($inland->coastal);
        $this->assertTrue($inland->peninsula);
        $this->assertSame('Miño de Pontevedra', $inland->name);
        // Zones come from every area of every message, verde included.
        $this->assertNotNull(AemetWarningZone::where('code', '711501')->first());

        $this->assertFalse(AemetFeedHealth::isStale('warnings'));
    }

    /** @test */
    public function a_repeated_snapshot_records_nothing(): void
    {
        $this->runJob();
        $events = AemetWarningEvent::count();
        $messages = AemetWarningMessage::count();
        $zones = AemetWarningZone::count();

        Carbon::setTestNow('2026-11-12T16:15:00Z');
        $this->runJob();

        $this->assertSame($events, AemetWarningEvent::count());
        $this->assertSame($messages, AemetWarningMessage::count());
        $this->assertSame($zones, AemetWarningZone::count());
        $this->assertSame(['645405C', '713201', '713603'], $this->activeZones());
    }

    /** @test */
    public function a_level_change_is_agravado(): void
    {
        $this->runJob();

        $files = $this->entries($this->fixture());
        unset($files['TEST.OLD.xml'], $files['TEST.NEW.xml']);
        $files[self::ZONE_713603] = str_replace(
            [
                '2.49.0.0.724.0.ES.20261112092714.713603PRP112231794475634',
                '<sent>2026-11-12T09:27:14-00:00</sent>',
                '<value>amarillo</value>',
            ],
            [
                '2.49.0.0.724.0.ES.20261112150000.713603PRP112231794475634',
                '<sent>2026-11-12T15:00:00-00:00</sent>',
                '<value>naranja</value>',
            ],
            $files[self::ZONE_713603]
        );

        Carbon::setTestNow('2026-11-12T16:15:00Z');
        $this->runJob($this->tar($files));

        $event = AemetWarningEvent::where('kind', 'agravado')->first();
        $this->assertNotNull($event);
        $this->assertSame('713603', $event->district);
        $this->assertSame('yellow', $event->fromLevel);
        $this->assertSame('orange', $event->toLevel);
        $this->assertSame(10, AemetWarningMessage::count(), 'the new message is archived, the old ones kept');
        $this->assertSame('orange', AemetWarning::whereNull('supersededAt')->where('district', '713603')->first()->level);
    }

    /** @test */
    public function a_tar_with_a_broken_file_changes_nothing(): void
    {
        $this->runJob();
        $before = AemetWarning::whereNull('supersededAt')->orderBy('district')->get(['district', 'level', 'endTime'])->toArray();
        $messages = AemetWarningMessage::count();

        $files = $this->entries($this->fixture());
        $files['garbage.xml'] = 'not a CAP message';

        Carbon::setTestNow('2026-11-12T16:15:00Z');
        $this->runJob($this->tar($files));

        $after = AemetWarning::whereNull('supersededAt')->orderBy('district')->get(['district', 'level', 'endTime'])->toArray();
        $this->assertEquals($before, $after);
        $this->assertSame(0, AemetWarningEvent::where('kind', 'retirado')->count());
        $this->assertSame($messages, AemetWarningMessage::count());
    }

    /** @test */
    public function an_empty_tar_ignores_the_snapshot(): void
    {
        $this->runJob();
        $before = $this->activeZones();

        Carbon::setTestNow('2026-11-12T16:15:00Z');
        $this->runJob($this->tar([]));
        $this->assertSame($before, $this->activeZones());
        $this->assertSame(0, AemetWarningEvent::where('kind', 'retirado')->count());
        $this->assertFalse(AemetFeedHealth::isStale('warnings'));

        // The last success was at 16:00; 61 minutes later the source is stale.
        Carbon::setTestNow('2026-11-12T17:01:00Z');
        $job = $this->runJob($this->tar([]));
        $this->assertTrue(AemetFeedHealth::isStale('warnings'));
        $this->assertStringStartsWith('AEMET: ', $job->healthMessage);
        $this->assertStringContainsString('snapshot vazio', $job->healthMessage);
        $this->assertSame($before, $this->activeZones());
    }

    /** @test */
    public function unauthorized_is_reported_with_its_own_message(): void
    {
        $this->runJob();

        Carbon::setTestNow('2026-11-12T17:01:00Z');
        $job = $this->job([new Response(401, [], '')]);
        $job->handle();

        $this->assertNotNull($job->healthMessage);
        $this->assertStringStartsWith('AEMET: ', $job->healthMessage);
        $this->assertStringContainsString('chave AEMET inválida ou expirada', $job->healthMessage);
        $this->assertSame(['645405C', '713201', '713603'], $this->activeZones());

        // Announced once; the recovery is announced too.
        Carbon::setTestNow('2026-11-12T17:16:00Z');
        $again = $this->job([new Response(401, [], '')]);
        $again->handle();
        $this->assertNull($again->healthMessage);

        $recovered = $this->runJob();
        $this->assertStringStartsWith('AEMET: ', $recovered->healthMessage);
        $this->assertStringContainsString('voltou a funcionar', $recovered->healthMessage);
    }

    /** @test */
    public function no_key_does_nothing(): void
    {
        config(['services.aemet.key' => '']);

        $this->job([])->handle();

        $this->assertCount(0, $this->history);
        $this->assertSame(0, AemetWarning::count());
        $this->assertSame(0, AemetWarningMessage::count());
    }

    /** @test */
    public function ipma_telegram_observer_is_not_triggered(): void
    {
        Queue::fake();

        // The IPMA observer posts warnings created for district STB.
        $files = $this->entries($this->fixture());
        $files[self::ZONE_713603] = str_replace('<value>713603</value>', '<value>STB</value>', $files[self::ZONE_713603]);
        $this->runJob($this->tar($files));

        $this->assertSame(1, AemetWarning::where('district', 'STB')->count());
        Queue::assertNotPushed(SendWeatherWarningToTelegram::class);
    }

    /** A tar of verde messages only (sent as given), the IPMA-style "nothing in force". */
    private function allClear(string $sent): string
    {
        $files = $this->entries($this->fixture());
        $verde = str_replace(
            ['<sent>2026-11-11T22:50:01-00:00</sent>', '2.49.0.0.724.0.ES.20261111225001.71VV71PRP114231794437401'],
            ['<sent>'.$sent.'</sent>', '2.49.0.0.724.0.ES.ALLCLEAR.71VV71PRP114231794437401'],
            $files[self::VERDE]
        );

        return $this->tar([self::VERDE => $verde]);
    }

    /** @test */
    public function an_all_clear_snapshot_withdraws_warnings(): void
    {
        $this->runJob();
        $this->assertSame(['645405C', '713201', '713603'], $this->activeZones());

        // Newer than the newest stored report (713201, sent 17:07:09Z).
        Carbon::setTestNow('2026-11-12T18:05:00Z');
        $job = $this->runJob($this->allClear('2026-11-12T18:00:00-00:00'));

        $this->assertNull($job->failureReason);
        $this->assertSame([], $this->activeZones());
        $this->assertSame(
            ['645405C', '713201', '713603'],
            AemetWarningEvent::where('kind', 'retirado')->pluck('district')->sort()->values()->all()
        );
        $this->assertSame(
            '2026-11-12T18:00:00+00:00',
            AemetWarningEvent::where('kind', 'retirado')->first()->occurredAt->toIso8601String()
        );
        $this->assertSame(10, AemetWarningMessage::count(), 'the all-clear message is archived');
        $this->assertFalse(AemetFeedHealth::isStale('warnings'));
        $this->assertSame(0, WeatherWarningEvent::count());
    }

    /** @test */
    public function a_partial_snapshot_is_a_failed_run(): void
    {
        $this->runJob();
        // 19 zones from the fixture + 20 more known from earlier snapshots.
        foreach (range(1, 20) as $i) {
            AemetWarningZone::create(['code' => (string) (750000 + $i), 'name' => 'Zona '.$i]);
        }
        $before = AemetWarning::whereNull('supersededAt')->orderBy('district')->get(['district', 'level', 'endTime'])->toArray();

        // The verde file alone covers 16 of the 39 known zones.
        Carbon::setTestNow('2026-11-12T16:30:00Z');
        $job = $this->runJob($this->allClear('2026-11-12T18:00:00-00:00'));

        $this->assertSame('snapshot parcial: 16 de 39 zonas', $job->failureReason);
        $after = AemetWarning::whereNull('supersededAt')->orderBy('district')->get(['district', 'level', 'endTime'])->toArray();
        $this->assertEquals($before, $after);
        $this->assertSame(0, AemetWarningEvent::where('kind', 'retirado')->count());
        $this->assertSame(39, AemetWarningZone::count(), 'no zone is removed');

        // Stale after the window like any other failure.
        Carbon::setTestNow('2026-11-12T17:01:00Z');
        $job = $this->runJob($this->allClear('2026-11-12T18:00:00-00:00'));
        $this->assertTrue(AemetFeedHealth::isStale('warnings'));
        $this->assertStringContainsString('snapshot parcial', $job->healthMessage);
    }

    /** @test */
    public function a_small_first_snapshot_is_accepted(): void
    {
        $job = $this->runJob($this->allClear('2026-11-12T15:00:00-00:00'));

        $this->assertNull($job->failureReason);
        $this->assertSame(16, AemetWarningZone::count());
        $this->assertFalse(AemetFeedHealth::isStale('warnings'));
    }

    /** @test */
    public function unauthorized_is_announced_at_once(): void
    {
        $job = $this->job([new Response(401, [], '')]);
        $job->handle();
        $this->assertStringStartsWith('AEMET: chave AEMET inválida ou expirada', $job->unauthorizedMessage);
        $this->assertNull($job->healthMessage, 'not stale yet');

        Carbon::setTestNow('2026-11-12T16:15:00Z');
        $again = $this->job([new Response(401, [], '')]);
        $again->handle();
        $this->assertNull($again->unauthorizedMessage);

        $this->runJob();
        Carbon::setTestNow('2026-11-12T16:30:00Z');
        $renewed = $this->job([new Response(401, [], '')]);
        $renewed->handle();
        $this->assertStringStartsWith('AEMET: chave AEMET inválida ou expirada', $renewed->unauthorizedMessage);
    }

    /** @test */
    public function an_older_all_clear_snapshot_is_ignored(): void
    {
        $this->runJob();
        $before = AemetWarning::whereNull('supersededAt')->orderBy('district')->get(['district', 'level', 'endTime'])->toArray();

        Carbon::setTestNow('2026-11-12T16:15:00Z');
        $job = $this->runJob($this->allClear('2026-11-12T08:00:00-00:00'));

        $this->assertNotNull($job->failureReason);
        $after = AemetWarning::whereNull('supersededAt')->orderBy('district')->get(['district', 'level', 'endTime'])->toArray();
        $this->assertEquals($before, $after);
        $this->assertSame(0, AemetWarningEvent::where('kind', 'retirado')->count());

        Carbon::setTestNow('2026-11-12T17:01:00Z');
        $job = $this->runJob($this->allClear('2026-11-12T08:00:00-00:00'));
        $this->assertTrue(AemetFeedHealth::isStale('warnings'));
        $this->assertStringContainsString('snapshot antigo', $job->healthMessage);
    }

    /** @test */
    public function the_command_fails_on_a_failed_run(): void
    {
        $this->app->instance(AemetTool::class, $this->tool($this->serving($this->tar([]))));
        $this->assertSame(1, Artisan::call('aemet:warnings'));
        $this->assertStringContainsString('snapshot vazio', Artisan::output());

        $this->app->instance(AemetTool::class, $this->tool($this->serving($this->fixture())));
        $this->assertSame(0, Artisan::call('aemet:warnings'));
    }

    /** @test */
    public function the_command_needs_a_key(): void
    {
        config(['services.aemet.key' => '']);
        $this->assertSame(1, Artisan::call('aemet:warnings'));
    }
}

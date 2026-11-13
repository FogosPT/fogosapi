<?php

namespace Tests\Unit\Tools;

use App\Tools\AemetException;
use App\Tools\AemetTool;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class AemetToolTest extends TestCase
{
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->history = [];
        config(['services.aemet.key' => 'test-key']);
    }

    private function tool(array $responses): AemetTool
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new AemetTool(new Client(['http_errors' => false, 'handler' => $stack]));
    }

    private function step1(string $datosUrl): Response
    {
        return new Response(200, [], json_encode([
            'descripcion' => 'exito',
            'estado' => 200,
            'datos' => $datosUrl,
            'metadatos' => 'https://opendata.aemet.es/opendata/sh/meta',
        ]));
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/fixtures/aemet/'.$name));
    }

    /** @test */
    public function it_follows_the_datos_url(): void
    {
        $tool = $this->tool([
            $this->step1('https://opendata.aemet.es/opendata/sh/abc123'),
            new Response(200, [], $this->fixture('observacion.json')),
        ]);

        $result = $tool->observations();

        $this->assertCount(2, $this->history);
        $this->assertSame(
            'https://opendata.aemet.es/opendata/api/observacion/convencional/todas',
            (string) $this->history[0]['request']->getUri()
        );
        $this->assertSame('test-key', $this->history[0]['request']->getHeaderLine('api_key'));
        $this->assertSame(
            'https://opendata.aemet.es/opendata/sh/abc123',
            (string) $this->history[1]['request']->getUri()
        );

        $stations = collect($result)->pluck('idema')->unique();
        $this->assertCount(3, $stations);

        $vandellos = collect($result)->firstWhere('idema', '0002I');
        $this->assertSame('VANDELLÓS', $vandellos['ubi']);
    }

    /** @test */
    public function empty_body_is_a_failure(): void
    {
        $tool = $this->tool([new Response(200, [], '')]);

        try {
            $tool->observations();
            $this->fail('Expected an AemetException.');
        } catch (AemetException $e) {
            $this->assertFalse($e->unauthorized);
        }
        $this->assertCount(1, $this->history);
    }

    /** @test */
    public function http_401_is_unauthorized(): void
    {
        $tool = $this->tool([new Response(401, [], '')]);

        try {
            $tool->observations();
            $this->fail('Expected an AemetException.');
        } catch (AemetException $e) {
            $this->assertTrue($e->unauthorized);
            $this->assertStringContainsString('chave AEMET inválida ou expirada', $e->getMessage());
        }
        $this->assertCount(1, $this->history);
    }

    /** @test */
    public function estado_not_200_is_a_failure(): void
    {
        $tool = $this->tool([
            new Response(200, [], json_encode(['estado' => 404, 'descripcion' => 'No hay datos'])),
        ]);

        try {
            $tool->observations();
            $this->fail('Expected an AemetException.');
        } catch (AemetException $e) {
            $this->assertFalse($e->unauthorized);
            $this->assertStringContainsString('No hay datos', $e->getMessage());
        }
    }

    /** @test */
    public function http_429_is_a_failure(): void
    {
        $tool = $this->tool([new Response(429, [], '')]);

        try {
            $tool->observations();
            $this->fail('Expected an AemetException.');
        } catch (AemetException $e) {
            $this->assertFalse($e->unauthorized);
        }
        $this->assertCount(1, $this->history);
    }

    /** @test */
    public function daily_builds_the_utc_range_path(): void
    {
        $tool = $this->tool([
            $this->step1('https://opendata.aemet.es/opendata/sh/daily123'),
            new Response(200, [], $this->fixture('diarios.json')),
        ]);

        $result = $tool->daily(Carbon::parse('2026-11-03'), Carbon::parse('2026-11-12'));

        $this->assertSame(
            'https://opendata.aemet.es/opendata/api/valores/climatologicos/diarios/datos/'
            .'fechaini/2026-11-03T00:00:00UTC/fechafin/2026-11-12T23:59:59UTC/todasestaciones',
            (string) $this->history[0]['request']->getUri()
        );
        $this->assertCount(5, $result);
    }

    /** @test */
    public function daily_uses_the_calendar_dates_in_summer(): void
    {
        // The app runs in Europe/Lisbon (UTC+1 in summer): the range must be
        // the calendar dates given, not their UTC-shifted days.
        $this->assertSame('Europe/Lisbon', date_default_timezone_get());

        $tool = $this->tool([
            $this->step1('https://opendata.aemet.es/opendata/sh/daily456'),
            new Response(200, [], $this->fixture('diarios.json')),
        ]);

        $tool->daily(Carbon::parse('2026-07-03'), Carbon::parse('2026-07-12'));

        $this->assertSame(
            'https://opendata.aemet.es/opendata/api/valores/climatologicos/diarios/datos/'
            .'fechaini/2026-07-03T00:00:00UTC/fechafin/2026-07-12T23:59:59UTC/todasestaciones',
            (string) $this->history[0]['request']->getUri()
        );
    }

    /** @test */
    public function warnings_tar_returns_raw_bytes(): void
    {
        $tarBytes = $this->fixture('avisos.tar');

        $tool = $this->tool([
            $this->step1('https://opendata.aemet.es/opendata/sh/tar123'),
            new Response(200, [], $tarBytes),
        ]);

        $result = $tool->warningsTar();

        $this->assertSame($tarBytes, $result);
        $this->assertSame(
            'https://opendata.aemet.es/opendata/api/avisos_cap/ultimoelaborado/area/esp',
            (string) $this->history[0]['request']->getUri()
        );
    }
}

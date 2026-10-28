<?php

namespace Tests\Feature\Seismic;

use App\Jobs\ProcessSeismicSource;
use App\Models\Earthquake;
use App\Support\SeismicFeedHealth;
use App\Tools\Seismic\IpmaSeismicTool;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ProcessSeismicSourceTest extends TestCase
{
    use DatabaseMigrations;

    private function ipma(array $responses): IpmaSeismicTool
    {
        return new IpmaSeismicTool(new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/fixtures/seismic/'.$name));
    }

    /** @test */
    public function both_ipma_areas_are_ingested_and_out_of_area_events_dropped(): void
    {
        $job = (new ProcessSeismicSource('ipma'))->withTool($this->ipma([
            new Response(200, [], $this->fixture('ipma-3.json')),
            new Response(200, [], $this->fixture('ipma-7.json')),
        ]));
        $job->handle();

        // 14 Azores + 3 of area 7 (the Fez, Morocco event is outside the bbox).
        $this->assertSame(17, $job->total);
        $this->assertSame(17, $job->created);
        $this->assertSame(17, Earthquake::count());
        $this->assertSame(1, Earthquake::where('region', 'madeira')->count());
        $this->assertTrue(Earthquake::where('region', 'continente')->first()->provisional);
        $this->assertFalse(SeismicFeedHealth::isStale('ipma'));
        $this->assertSame('ipma', SeismicFeedHealth::primary());
    }

    /** @test */
    public function a_failing_source_is_recorded_and_emsc_becomes_primary(): void
    {
        SeismicFeedHealth::recordSuccess('emsc', 3);

        $job = (new ProcessSeismicSource('ipma'))->withTool($this->ipma([
            new ConnectException('timeout', new Request('GET', 'https://api.ipma.pt')),
        ]));
        $job->handle();

        $this->assertSame(0, Earthquake::count());
        $this->assertTrue(SeismicFeedHealth::isStale('ipma'));
        $this->assertSame('emsc', SeismicFeedHealth::primary());
    }

    /** @test */
    public function an_html_error_page_behind_a_200_counts_as_a_failure(): void
    {
        SeismicFeedHealth::recordSuccess('emsc', 3);

        $job = (new ProcessSeismicSource('ipma'))->withTool($this->ipma([
            new Response(200, ['Content-Type' => 'text/html'], '<html><body>Service Unavailable</body></html>'),
        ]));
        $job->handle();

        $this->assertTrue(SeismicFeedHealth::isStale('ipma'));
        $this->assertSame('emsc', SeismicFeedHealth::primary());
    }

    /** @test */
    public function a_valid_but_empty_feed_keeps_the_source_healthy(): void
    {
        $job = (new ProcessSeismicSource('ipma'))->withTool($this->ipma([
            new Response(200, [], '{"idArea": 3, "data": []}'),
            new Response(200, [], '{"idArea": 7, "data": []}'),
        ]));
        $job->handle();

        $this->assertSame(0, $job->total);
        $this->assertFalse(SeismicFeedHealth::isStale('ipma'));
    }
}

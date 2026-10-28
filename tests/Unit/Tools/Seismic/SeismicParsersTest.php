<?php

namespace Tests\Unit\Tools\Seismic;

use App\Tools\Seismic\EmscTool;
use App\Tools\Seismic\GeofonTool;
use App\Tools\Seismic\IgnTool;
use App\Tools\Seismic\InvalidSeismicPayload;
use App\Tools\Seismic\IpmaSeismicTool;
use App\Tools\Seismic\SeismicEvent;
use App\Tools\Seismic\UsgsTool;
use Tests\TestCase;

/**
 * Fixtures are real responses recorded on 2026-10-28 (trimmed). The
 * 2026-10-20 18:07 UTC Azores M5 event is present in IPMA, EMSC, USGS and
 * GEOFON.
 */
class SeismicParsersTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/fixtures/seismic/'.$name));
    }

    /**
     * @param SeismicEvent[] $events
     */
    private function at(array $events, string $time): SeismicEvent
    {
        foreach ($events as $e) {
            if (str_starts_with($e->time->format('Y-m-d\TH:i:s'), $time)) {
                return $e;
            }
        }
        $this->fail("No event at {$time}");
    }

    /** @test */
    public function ipma_events_are_parsed_with_felt_information(): void
    {
        $events = (new IpmaSeismicTool())->parse($this->fixture('ipma-3.json'));

        $this->assertCount(14, $events);
        $e = $this->at($events, '2026-10-20T18:07:19');
        $this->assertSame('ipma', $e->source);
        $this->assertSame('20261020180718A', $e->sourceId);
        $this->assertSame('UTC', $e->time->timezoneName);
        $this->assertEqualsWithDelta(37.854, $e->lat, 1e-6);
        $this->assertEqualsWithDelta(-26.272, $e->lon, 1e-6);
        $this->assertSame(5.3, $e->magnitude);
        $this->assertSame('MW', $e->magType);
        $this->assertTrue($e->felt);
        $this->assertSame('IV/V', $e->intensity);
        $this->assertSame('S.Miguel: Ginetes (Ponta Delgada)', $e->feltLocal);
        $this->assertSame('Fossa de Hirondelle', $e->place);
    }

    /** @test */
    public function ipma_falls_back_to_origin_time_when_sismo_id_is_empty(): void
    {
        $events = (new IpmaSeismicTool())->parse($this->fixture('ipma-3.json'));

        $this->assertSame('20261020182256', $this->at($events, '2026-10-20T18:22:56')->sourceId);
    }

    /** @test */
    public function ipma_minus_99_magnitude_is_provisional(): void
    {
        $events = (new IpmaSeismicTool())->parse($this->fixture('ipma-7.json'));

        $e = $this->at($events, '2026-09-29T23:08:31');
        $this->assertNull($e->magnitude);
        $this->assertTrue($e->extra['provisional']);
        $this->assertSame('W Monchique', $e->place);

        $braganca = $this->at($events, '2026-10-15T20:34:46');
        $this->assertSame('http://shakemap.ipma.pt/2026101520344601/intensity.html', $braganca->extra['shakemapref']);
        $this->assertFalse($braganca->extra['provisional']);
    }

    /** @test */
    public function emsc_features_are_parsed(): void
    {
        $events = (new EmscTool())->parse($this->fixture('emsc.json'));

        $this->assertCount(7, $events);
        $e = $this->at($events, '2026-10-20T18:07:18');
        $this->assertSame('emsc', $e->source);
        $this->assertSame(5.1, $e->magnitude);
        $this->assertSame('MW', $e->magType);
        $this->assertSame('AZORES ISLANDS, PORTUGAL', $e->place);
        $this->assertSame(920, (int) $e->time->format('v'));
    }

    /** @test */
    public function usgs_features_keep_dyfi_and_products(): void
    {
        $events = (new UsgsTool())->parse($this->fixture('usgs.json'));

        $this->assertCount(1, $events);
        $e = $events[0];
        $this->assertSame('us6000u3p1', $e->sourceId);
        $this->assertSame('2026-10-20T18:07:18', $e->time->format('Y-m-d\TH:i:s'));
        $this->assertSame(5.1, $e->magnitude);
        $this->assertSame('MWW', $e->magType);
        $this->assertEqualsWithDelta(10.0, $e->depth, 1e-6);
        $this->assertTrue($e->felt);
        $this->assertSame(14, $e->extra['feltReports']);
        $this->assertContains('moment-tensor', $e->extra['products']);
        $this->assertArrayNotHasKey('tsunami', $e->extra);
    }

    /** @test */
    public function geofon_text_is_parsed(): void
    {
        $events = (new GeofonTool())->parse($this->fixture('geofon.txt'));

        $this->assertCount(1, $events);
        $this->assertSame('gfz2026uowt', $events[0]->sourceId);
        $this->assertSame('Mw', $events[0]->magType);
        $this->assertSame(5.1, $events[0]->magnitude);
        $this->assertSame('Azores Islands, Portugal', $events[0]->place);
    }

    /** @test */
    public function ign_rss_descriptions_are_parsed(): void
    {
        $events = (new IgnTool())->parse($this->fixture('ign.xml'));

        $this->assertCount(27, $events);
        $e = $this->at($events, '2026-10-27T19:54:49');
        $this->assertSame('SW CABO DE SAN VICENTE', $e->place);
        $this->assertSame(2.9, $e->magnitude);
        $this->assertSame('es2026vbxxb', $e->sourceId);
        $this->assertEqualsWithDelta(36.8316, $e->lat, 1e-6);
    }

    /** @test */
    public function garbage_payloads_are_rejected_not_treated_as_empty(): void
    {
        foreach ([new IpmaSeismicTool(), new EmscTool(), new UsgsTool(), new GeofonTool(), new IgnTool()] as $tool) {
            foreach (['<html>502 Bad Gateway</html>', '{"truncated": '] as $body) {
                try {
                    $tool->parse($body);
                    $this->fail($tool->name().' accepted '.$body);
                } catch (InvalidSeismicPayload $e) {
                    $this->assertStringStartsWith($tool->name().':', $e->getMessage());
                }
            }
        }
    }

    /** @test */
    public function valid_empty_catalogues_yield_no_events(): void
    {
        $this->assertSame([], (new IpmaSeismicTool())->parse('{"idArea": 3, "data": []}'));
        $this->assertSame([], (new EmscTool())->parse(''));
        $this->assertSame([], (new UsgsTool())->parse('{"type": "FeatureCollection", "features": []}'));
        $this->assertSame([], (new GeofonTool())->parse(''));
        $this->assertSame([], (new GeofonTool())->parse("#EventID|Time|Latitude|Longitude|Depth/km|Author|Catalog|Contributor|ContributorID|MagType|Magnitude|MagAuthor|EventLocationName|EventType\n"));
        $this->assertSame([], (new IgnTool())->parse('<?xml version="1.0"?><rss version="2.0"><channel><title>x</title></channel></rss>'));
    }
}

<?php

namespace Tests\Feature\Controllers;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class FirePerimetersControllerTest extends TestCase
{
    /** @test */
    public function it_preserves_multipolygon_geometry_and_injects_disclaimer(): void
    {
        $feature = [
            'type'     => 'Feature',
            'geometry' => [
                'type'        => 'MultiPolygon',
                'coordinates' => [
                    [[[-8.6, 41.1], [-8.6, 41.2], [-8.5, 41.2], [-8.5, 41.1], [-8.6, 41.1]]],
                    [[[-8.0, 40.5], [-8.0, 40.6], [-7.9, 40.6], [-7.9, 40.5], [-8.0, 40.5]]],
                ],
            ],
            'properties' => [
                'fogospt_incident' => ['id' => '2026123456789'],
                'detections'       => 12,
            ],
        ];

        $payload = json_encode([
            'type'     => 'FeatureCollection',
            'features' => [$feature],
        ]);

        Redis::shouldReceive('get')->with('mtg:perimeters')->once()->andReturn($payload);
        Redis::shouldReceive('get')->with('mtg:perimeters:fetched_at')->once()->andReturn('2026-09-24T10:00:00+00:00');

        $response = $this->getJson('/v2/fire/perimeters');

        $response->assertStatus(200);
        $response->assertJsonPath('features.0.geometry.type', 'MultiPolygon');
        $response->assertJsonPath('features.0.geometry.coordinates.0.0.0.0', -8.6);
        $response->assertJsonPath('features.0.properties.detections', 12);
        $response->assertJsonPath('features.0.properties.fogospt_incident.id', '2026123456789');

        $topDisclaimer = $response->json('disclaimer');
        $this->assertNotEmpty($topDisclaimer);
        $this->assertStringContainsString('MTG', $topDisclaimer);

        $featureDisclaimer = $response->json('features.0.properties.disclaimer');
        $this->assertNotEmpty($featureDisclaimer);
    }

    /** @test */
    public function it_does_not_500_on_null_geometry_and_retained_geometry_false(): void
    {
        $feature = [
            'type'     => 'Feature',
            'geometry' => null,
            'properties' => [
                'fogospt_incident'   => ['id' => '2026999999999'],
                'retained_geometry'  => false,
                'detections'         => 0,
            ],
        ];

        $payload = json_encode([
            'type'     => 'FeatureCollection',
            'features' => [$feature],
        ]);

        Redis::shouldReceive('get')->with('mtg:perimeters')->once()->andReturn($payload);
        Redis::shouldReceive('get')->with('mtg:perimeters:fetched_at')->once()->andReturn('2026-09-24T10:00:00+00:00');

        $response = $this->getJson('/v2/fire/perimeters');

        $response->assertStatus(200);
        $response->assertJsonPath('features.0.geometry', null);
        $response->assertJsonPath('features.0.properties.retained_geometry', false);
        $response->assertJsonPath('features.0.properties.fogospt_incident.id', '2026999999999');

        $featureDisclaimer = $response->json('features.0.properties.disclaimer');
        $this->assertNotEmpty($featureDisclaimer);
    }

    /** @test */
    public function it_marks_stale_snapshots_when_fetched_at_is_older_than_thirty_minutes(): void
    {
        $payload = json_encode([
            'type'     => 'FeatureCollection',
            'features' => [],
        ]);

        $oldTimestamp = now()->subHour()->toIso8601String();

        Redis::shouldReceive('get')->with('mtg:perimeters')->once()->andReturn($payload);
        Redis::shouldReceive('get')->with('mtg:perimeters:fetched_at')->once()->andReturn($oldTimestamp);

        $response = $this->getJson('/v2/fire/perimeters');

        $response->assertStatus(200);
        $response->assertHeader('X-Stale', 'true');
    }

    /** @test */
    public function it_does_not_mark_fresh_snapshots_as_stale(): void
    {
        $payload = json_encode([
            'type'     => 'FeatureCollection',
            'features' => [],
        ]);

        $freshTimestamp = now()->subMinutes(5)->toIso8601String();

        Redis::shouldReceive('get')->with('mtg:perimeters')->once()->andReturn($payload);
        Redis::shouldReceive('get')->with('mtg:perimeters:fetched_at')->once()->andReturn($freshTimestamp);

        $response = $this->getJson('/v2/fire/perimeters');

        $response->assertStatus(200);
        $this->assertFalse($response->headers->has('X-Stale'));
    }

    /** @test */
    public function it_returns_empty_feature_collection_when_redis_is_empty(): void
    {
        Redis::shouldReceive('get')->with('mtg:perimeters')->once()->andReturn(null);

        $response = $this->getJson('/v2/fire/perimeters');

        $response->assertStatus(200);
        $response->assertJsonPath('type', 'FeatureCollection');
        $response->assertJsonPath('features', []);
    }

    /** @test */
    public function it_serves_the_uncorrelated_snapshot_with_its_own_disclaimer(): void
    {
        $feature = [
            'type'       => 'Feature',
            'geometry'   => ['type' => 'Polygon', 'coordinates' => [[[-8.0, 40.0], [-8.0, 40.1], [-7.9, 40.1], [-8.0, 40.0]]]],
            'properties' => [
                'fogospt_incident' => null,
                'detections'       => 3,
            ],
        ];

        $payload = json_encode([
            'type'     => 'FeatureCollection',
            'features' => [$feature],
        ]);

        Redis::shouldReceive('get')->with('mtg:events_uncorrelated')->once()->andReturn($payload);
        Redis::shouldReceive('get')->with('mtg:events_uncorrelated:fetched_at')->once()->andReturn('2026-09-24T10:00:00+00:00');

        $response = $this->getJson('/v2/fire/perimeters/uncorrelated');

        $response->assertStatus(200);
        $response->assertJsonPath('features.0.properties.fogospt_incident', null);

        $topDisclaimer = $response->json('disclaimer');
        $this->assertNotEmpty($topDisclaimer);
        $this->assertStringContainsString('por confirmar', $topDisclaimer);
    }
}

<?php

namespace Tests\Feature\Seismic;

use App\Models\Earthquake;
use App\Support\EarthquakeMerger;
use App\Support\SeismicPlaces;
use App\Tools\Seismic\SeismicEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use MongoDB\BSON\UTCDateTime;

class SeismicPlacesTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function the_vinhais_earthquake_is_north_east_of_a_place_in_braganca_district(): void
    {
        // 15/10/2026 M4.2 "NE Bragança", epicentre in Spain, felt in Vinhais.
        $places = SeismicPlaces::nearest(42.462, -6.602);

        $this->assertCount(3, $places);
        $this->assertContains($places[0]['municipality'], ['Vinhais', 'Bragança']);
        $this->assertSame('freguesia', $places[0]['kind']);
        $this->assertContains($places[0]['bearing'], ['N', 'NE']);
        // The epicentre is ~50 km inside Spain.
        $this->assertLessThan(75, $places[0]['km']);
        $this->assertSame('concelho', $places[1]['kind']);
        $this->assertNotSame($places[0]['municipality'], $places[2]['municipality']);
    }

    /** @test */
    public function an_epicentre_near_ponta_delgada_names_sao_miguel_places(): void
    {
        $places = SeismicPlaces::nearest(37.75, -25.70);

        $this->assertSame('Ponta Delgada', $places[0]['municipality']);
        $this->assertLessThan(8, $places[0]['km']);
    }

    /** @test */
    public function offshore_south_west_is_measured_from_the_algarve_corner(): void
    {
        // South-west of Cabo de São Vicente.
        $places = SeismicPlaces::nearest(36.3, -10.0);
        $first = $places[0];

        $this->assertSame('Vila do Bispo', $first['municipality']);
        $this->assertSame('SO', $first['bearing']);
        $this->assertEqualsWithDelta(115, $first['km'], 25);
        // Farther west it is due west (≈ 248° rounds to O).
        $this->assertSame('O', SeismicPlaces::nearest(36.5, -10.5)[0]['bearing']);
    }

    /** @test */
    public function bearings_use_portuguese_compass_points(): void
    {
        $this->assertSame('N', SeismicPlaces::bearing(38.0, -9.0, 39.0, -9.0));
        $this->assertSame('O', SeismicPlaces::bearing(38.0, -9.0, 38.0, -10.0));
        $this->assertSame('SO', SeismicPlaces::bearing(38.0, -9.0, 37.3, -9.9));
        $this->assertSame('E', SeismicPlaces::bearing(38.0, -9.0, 38.0, -8.0));
    }

    /** @test */
    public function the_merger_stores_nearest_places_and_the_api_exposes_them(): void
    {
        [$q] = (new EarthquakeMerger())->upsert(new SeismicEvent('ipma', 'v1', Carbon::now(), 42.462, -6.602, 16.0, 4.2, 'ML', 'NE Bragança'));

        $this->assertCount(3, $q->fresh()->nearestPlaces);

        $res = $this->getJson('/v2/seismic/events/'.$q->id)->assertOk();
        $this->assertSame($q->nearestPlaces[0]['name'], $res->json('data.nearest.name'));
        $this->assertCount(3, $res->json('data.nearestPlaces'));
        $this->assertNotNull($this->getJson('/v2/seismic/events')->json('data.0.nearest'));
    }

    /** @test */
    public function the_command_fills_stored_earthquakes(): void
    {
        DB::connection('mongodb')->getMongoDB()->selectCollection('earthquakes')->insertMany([
            ['lat' => 37.75, 'lon' => -25.70, 'time' => new UTCDateTime()],
            ['lat' => 36.5, 'lon' => -10.5, 'time' => new UTCDateTime()],
        ]);

        $this->artisan('seismic:places --chunk=1')->assertSuccessful();
        $this->assertSame(2, Earthquake::whereNotNull('nearestPlaces')->count());

        // --missing skips what is already filled.
        $this->artisan('seismic:places --missing')->expectsOutputToContain('0 earthquakes')->assertSuccessful();
    }
}

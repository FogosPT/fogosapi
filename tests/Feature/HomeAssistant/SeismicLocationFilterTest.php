<?php

namespace Tests\Feature\HomeAssistant;

use App\Models\Location;
use App\Support\EarthquakeMerger;
use App\Tools\Seismic\SeismicEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class SeismicLocationFilterTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-28T16:00:00Z');

        Location::create(['level' => 1, 'code' => 42, 'name' => 'Ilha de São Miguel']);
        Location::create(['level' => 2, 'code' => 4203, 'name' => 'Ponta Delgada']);
        Location::create(['level' => 2, 'code' => 4206, 'name' => 'Ribeira Grande']);
        Location::create(['level' => 2, 'code' => 1106, 'name' => 'Lisboa']);
        Location::create(['level' => 3, 'code' => 110601, 'name' => 'Belém']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function quake(string $id, int $hour, array $places): void
    {
        // Hours apart, so the merger does not take them for one event.
        $time = Carbon::parse('2026-10-27T00:00:00Z')->addHours($hour);
        [$q] = (new EarthquakeMerger())->upsert(new SeismicEvent('ipma', $id, $time, 37.8, -25.6 + $hour, 10.0, 3.0, 'ML', 'Teste'));
        $q->nearestPlaces = $places;
        $q->save();
    }

    private function place(string $name, string $municipality): array
    {
        return ['name' => $name, 'municipality' => $municipality, 'kind' => 'f', 'km' => 5.0, 'bearing' => 'N'];
    }

    private function places(string $uri): array
    {
        return array_map(fn ($e) => $e['nearest']['name'], $this->getJson($uri)->assertOk()->json('data'));
    }

    /** @test */
    public function events_and_latest_filter_by_the_nearest_places(): void
    {
        $this->quake('a1', 1, [$this->place('Fenais da Luz', 'Ponta Delgada')]);
        $this->quake('a2', 4, [$this->place('Rabo de Peixe', 'Ribeira Grande')]);
        $this->quake('a3', 7, [$this->place('Belém', 'Lisboa')]);
        $this->quake('a4', 10, [$this->place('Sines', 'Sines')]);

        foreach (['v2/seismic/events', 'v2/seismic/events/latest'] as $uri) {
            $this->assertEqualsCanonicalizing(['Fenais da Luz', 'Rabo de Peixe'], $this->places($uri.'?district='.urlencode('Ilha de São Miguel')));
            $this->assertEqualsCanonicalizing(['Fenais da Luz', 'Belém'], $this->places($uri.'?concelho='.urlencode('ponta delgada|Lisboa')));
            $this->assertSame(['Belém'], $this->places($uri.'?freguesia='.urlencode('Belém')));
            $this->assertCount(4, $this->places($uri));
        }

        $this->getJson('v2/seismic/events?concelho=Narnia')->assertStatus(422);
    }
}

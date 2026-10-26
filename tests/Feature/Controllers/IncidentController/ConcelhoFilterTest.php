<?php

namespace Tests\Feature\Controllers\IncidentController;

use App\Models\Location;
use Database\Factories\IncidentFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConcelhoFilterTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        Location::create(['level' => 2, 'code' => 1317, 'name' => 'Vila Nova de Gaia']);
        Location::create(['level' => 2, 'code' => 1106, 'name' => 'Lisboa']);
    }

    /** @test */
    public function it_returns_422_for_an_unknown_concelho(): void
    {
        foreach (['v2/incidents/active', 'v2/incidents/search'] as $uri) {
            $this->getJson($uri.'?concelho=???')
                ->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error' => 'invalid_concelho',
                ]);
        }
    }

    /** @test */
    public function it_returns_422_when_concelho_is_not_a_string(): void
    {
        $this->getJson('v2/incidents/active?concelho[]=Lisboa')
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_concelho']);
    }

    /** @test */
    public function it_filters_by_concelho_case_insensitively(): void
    {
        $gaia = IncidentFactory::new()->active()->fire()->create(['concelho' => 'Vila Nova De Gaia']);
        IncidentFactory::new()->active()->fire()->create(['concelho' => 'Lisboa']);

        foreach (['Vila Nova de Gaia', 'vila nova de gaia', 'Vila Nova De Gaia'] as $concelho) {
            $this->getJson('v2/incidents/active?concelho='.urlencode($concelho))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $gaia->id);
        }
    }

    /** @test */
    public function it_returns_an_empty_list_for_a_known_concelho_without_fires(): void
    {
        IncidentFactory::new()->active()->fire()->create(['concelho' => 'Vila Nova De Gaia']);

        $this->getJson('v2/incidents/active?concelho=Lisboa')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function it_does_not_filter_when_concelho_is_absent_or_empty(): void
    {
        IncidentFactory::new()->active()->fire()->create(['concelho' => 'Vila Nova De Gaia']);
        IncidentFactory::new()->active()->fire()->create(['concelho' => 'Lisboa']);

        $this->getJson('v2/incidents/active')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('v2/incidents/active?concelho=')->assertOk()->assertJsonCount(2, 'data');
    }
}

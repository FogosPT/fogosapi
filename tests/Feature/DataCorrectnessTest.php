<?php

namespace Tests\Feature;

use App\Models\IncidentStatusHistory;
use Database\Factories\IncidentFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DataCorrectnessTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    /** @test */
    public function icnf_unknown_means_are_not_subtracted_from_public_totals(): void
    {
        $base = ['statusCode' => 5, 'statusColor' => 'B81E1F'];
        IncidentFactory::new()->active()->fire()->create($base + ['id' => '1', 'man' => 40, 'terrain' => 10, 'aerial' => 2]);
        // ICNF-only fire: -1 means "unknown".
        IncidentFactory::new()->active()->fire()->create($base + ['id' => '2', 'man' => -1, 'terrain' => -1, 'aerial' => -1]);

        $this->getJson('v1/now')
            ->assertOk()
            ->assertJsonPath('data.man', 40)
            ->assertJsonPath('data.cars', 10)
            ->assertJsonPath('data.aerial', 2)
            ->assertJsonPath('data.total', 2);
    }

    /** @test */
    public function extended_search_includes_status_history(): void
    {
        IncidentFactory::new()->fire()->create([
            'id' => '2026100',
            'dateTime' => now(),
        ]);
        IncidentStatusHistory::create(['incidentId' => '2026100', 'status' => 'Em Curso', 'statusCode' => 5]);

        $response = $this->getJson('v2/incidents/search?extend=1')->assertOk();

        $this->assertNotEmpty($response->json('data.0.statusHistory'));
    }

    /** @test */
    public function bad_input_returns_4xx_instead_of_500(): void
    {
        $this->getJson('fires/data')->assertNotFound();
        $this->getJson('fires/danger')->assertNotFound();
        $this->getJson('fires/status')->assertNotFound();
        $this->getJson('v2/weather/daily?date=not-a-date')->assertStatus(422);
        $this->getJson('v2/stats/today/ignitions-hourly?day=not-a-date')->assertStatus(422);
    }
}

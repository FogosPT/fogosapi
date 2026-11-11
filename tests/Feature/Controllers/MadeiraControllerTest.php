<?php

namespace Tests\Feature\Controllers;

use App\Jobs\ProcessMadeiraEvents;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MadeiraControllerTest extends TestCase
{
    use DatabaseMigrations;

    private string $id;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $feed = json_decode(file_get_contents(base_path('tests/fixtures/madeira/dados.json')), true);
        $job = new ProcessMadeiraEvents();
        $job->sync($feed);
        $job->sync(array_replace_recursive($feed, ['atualizado' => '2026-11-11T09:30', 'kpi' => ['ativas' => 12]]));

        $this->id = md5('Depressão Charles – Precipitação e vento forte|2026-11-10T20:00');
    }

    /** @test */
    public function current_returns_the_active_event_with_its_breakdowns(): void
    {
        $this->getJson('v2/madeira/events/current')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->id)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.start', '2026-11-10T20:00:00Z')
            ->assertJsonPath('data.updatedAt', '2026-11-11T09:30:00Z')
            ->assertJsonPath('data.kpi.activeOccurrences', 12)
            ->assertJsonCount(12, 'data.municipalities')
            ->assertJsonCount(11, 'data.natures')
            ->assertJsonCount(14, 'data.hourly')
            ->assertJsonCount(5, 'data.recommendations');
    }

    /** @test */
    public function current_is_null_when_no_event_is_active(): void
    {
        $feed = json_decode(file_get_contents(base_path('tests/fixtures/madeira/dados.json')), true);
        (new ProcessMadeiraEvents())->sync(array_replace_recursive($feed, [
            'evento' => ['estado' => 'ENCERRADO'],
            'atualizado' => '2026-11-11T12:00',
        ]));

        $this->getJson('v2/madeira/events/current')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    /** @test */
    public function events_lists_summaries_without_breakdowns(): void
    {
        $this->getJson('v2/madeira/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->id)
            ->assertJsonPath('data.0.kpi.occurrences', 212)
            ->assertJsonMissingPath('data.0.municipalities');

        $this->getJson('v2/madeira/events?limit=0')->assertStatus(422);
        $this->getJson('v2/madeira/events?limit=abc')->assertStatus(422);
    }

    /** @test */
    public function show_returns_one_event_or_404(): void
    {
        $this->getJson('v2/madeira/events/'.$this->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Depressão Charles – Precipitação e vento forte')
            ->assertJsonPath('data.municipalities.0.name', 'Porto Moniz');

        $this->getJson('v2/madeira/events/'.str_repeat('0', 32))->assertNotFound();
        $this->getJson('v2/madeira/events/not-an-id')->assertNotFound();
    }

    /** @test */
    public function timeline_returns_the_kpis_of_every_update_oldest_first(): void
    {
        $this->getJson('v2/madeira/events/'.$this->id.'/timeline')
            ->assertOk()
            ->assertJsonPath('eventId', $this->id)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.time', '2026-11-11T09:25:00Z')
            ->assertJsonPath('data.0.activeOccurrences', 18)
            ->assertJsonPath('data.1.time', '2026-11-11T09:30:00Z')
            ->assertJsonPath('data.1.activeOccurrences', 12)
            ->assertJsonPath('data.1.operationals', 553);

        $this->getJson('v2/madeira/events/'.str_repeat('0', 32).'/timeline')->assertNotFound();
    }
}

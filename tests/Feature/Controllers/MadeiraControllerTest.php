<?php

namespace Tests\Feature\Controllers;

use App\Jobs\ProcessMadeiraEvents;
use App\Jobs\ProcessMadeiraOccurrences;
use Carbon\Carbon;
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

        $occurrences = json_decode(file_get_contents(base_path('tests/fixtures/madeira/ocorrencias.json')), true);
        (new ProcessMadeiraOccurrences())->sync(array_column($occurrences['features'], 'attributes'));

        Carbon::setTestNow(Carbon::parse('2026-11-11T09:30:00Z'));

        $this->id = md5('Depressão Charles – Precipitação e vento forte|2026-11-10T20:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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
            ->assertJsonCount(5, 'data.recommendations')
            ->assertJsonPath('data.roadRestrictions', [])
            ->assertJsonCount(5, 'data.communiques')
            ->assertJsonPath('data.communiques.0.official', true)
            ->assertJsonPath('data.communiques.0.time', '2026-11-10T15:27:00Z');
    }

    /** @test */
    public function the_event_detail_adds_parishes_entities_and_top_occurrences(): void
    {
        $response = $this->getJson('v2/madeira/events/'.$this->id)->assertOk();

        // Occurrences inside the period with one of the event's natures.
        $this->assertSame(214, collect($response->json('data.parishes'))->sum('occurrences'));
        $response
            ->assertJsonPath('data.parishes.0.code', '310403')
            ->assertJsonPath('data.parishes.0.name', 'Machico')
            ->assertJsonPath('data.parishes.0.municipality', 'Machico')
            ->assertJsonPath('data.parishes.0.occurrences', 28)
            ->assertJsonPath('data.entities.0', ['code' => 'CMOS', 'occurrences' => 85])
            ->assertJsonCount(10, 'data.topOccurrences')
            ->assertJsonPath('data.topOccurrences.0.id', 16015)
            ->assertJsonPath('data.topOccurrences.0.vehicles', 3)
            ->assertJsonPath('data.topOccurrences.0.firefighters', 6)
            ->assertJsonPath('data.topOccurrences.0.parish', 'Caniço')
            ->assertJsonPath('data.topOccurrences.0.entities', ['CMOS', 'BMSC']);

        $this->assertNotNull(collect($response->json('data.parishes'))->firstWhere('code', null), 'occurrences without parish are grouped');
    }

    /** @test */
    public function occurrences_default_to_the_last_24_hours_newest_first(): void
    {
        $this->getJson('v2/madeira/occurrences')
            ->assertOk()
            ->assertJsonCount(224, 'data')
            ->assertJsonPath('data.0.id', 16172)
            ->assertJsonStructure(['data' => [['id', 'start', 'natureCode', 'nature', 'firefighters', 'vehicles', 'parishCode', 'parish', 'municipalityCode', 'municipality', 'entities', 'updatedAt']]]);
    }

    /** @test */
    public function occurrences_can_be_filtered_and_are_validated(): void
    {
        $this->getJson('v2/madeira/occurrences?municipality=3103')->assertOk()->assertJsonCount(48, 'data');
        $this->getJson('v2/madeira/occurrences?nature=3315&from=2026-01-01')->assertOk()->assertJsonCount(61, 'data');
        $this->getJson('v2/madeira/occurrences?parish=310403&limit=5')->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.municipality', 'Machico');
        $this->getJson('v2/madeira/occurrences?from=2026-11-11T09:00:00Z&to=2026-11-11T09:10:00Z')->assertOk()
            ->assertJsonPath('data', fn ($rows) => collect($rows)->every(fn ($r) => $r['start'] >= '2026-11-11T09:00:00Z' && $r['start'] <= '2026-11-11T09:10:00Z') && count($rows) > 0);

        $this->getJson('v2/madeira/occurrences?municipality=1106')->assertStatus(422);
        $this->getJson('v2/madeira/occurrences?parish=abc')->assertStatus(422);
        $this->getJson('v2/madeira/occurrences?limit=5000')->assertStatus(422);
        $this->getJson('v2/madeira/occurrences?from=ontem')->assertStatus(422);
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
            ->assertJsonMissingPath('data.0.municipalities')
            ->assertJsonMissingPath('data.0.communiques');

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

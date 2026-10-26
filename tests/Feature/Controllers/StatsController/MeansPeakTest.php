<?php

namespace Tests\Feature\Controllers\StatsController;

use App\Models\Incident;
use App\Models\IncidentHistory;
use Carbon\Carbon;
use Database\Factories\IncidentFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use MongoDB\BSON\UTCDateTime;
use Tests\TestCase;

class MeansPeakTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    /** @test */
    public function it_validates_the_interval(): void
    {
        $this->getJson('v2/stats/means-peak')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'invalid_parameters']);

        $this->getJson('v2/stats/means-peak?after=2025-08-31&before=2025-08-01')
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_parameters']);

        $this->getJson('v2/stats/means-peak?after=2025-08-01&before=31-08-2025')
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_parameters']);

        $this->getJson('v2/stats/means-peak?after=2023-01-01&before=2025-01-01')
            ->assertStatus(422)
            ->assertJson(['error' => 'interval_too_long']);
    }

    /** @test */
    public function it_returns_the_peak_of_each_kind_of_means_with_time_and_incident(): void
    {
        $this->incident('1001', 'Lisboa', '2025-08-10 14:00');
        $this->history('1001', 50, 10, 1, '2025-08-10 14:30');
        $this->history('1001', 60, 14, 2, '2025-08-10 16:00');
        $this->history('1001', 40, 8, 2, '2025-08-10 19:00');

        $this->incident('1002', 'Porto', '2025-08-20 11:00');
        $this->history('1002', 35, 7, 12, '2025-08-20 13:15');

        // Started outside the interval: ignored even with bigger numbers.
        $this->incident('1003', 'Porto', '2025-07-15 10:00');
        $this->history('1003', 500, 100, 30, '2025-08-02 10:00');

        $this->getJson('v2/stats/means-peak?after=2025-08-01&before=2025-08-31')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.incidents', 2)
            ->assertJsonPath('data.man.value', 60)
            ->assertJsonPath('data.man.at', '2025-08-10T16:00:00+01:00')
            ->assertJsonPath('data.man.incident.id', '1001')
            ->assertJsonPath('data.terrain.value', 14)
            ->assertJsonPath('data.terrain.incident.id', '1001')
            ->assertJsonPath('data.aerial.value', 12)
            ->assertJsonPath('data.aerial.at', '2025-08-20T13:15:00+01:00')
            ->assertJsonPath('data.aerial.incident.id', '1002');
    }

    /** @test */
    public function it_filters_by_location_case_insensitively(): void
    {
        $this->incident('1001', 'Lisboa', '2025-08-10 14:00');
        $this->history('1001', 60, 14, 2, '2025-08-10 16:00');

        $this->incident('1002', 'Porto', '2025-08-20 11:00');
        $this->history('1002', 35, 7, 12, '2025-08-20 13:15');

        $this->getJson('v2/stats/means-peak?after=2025-08-01&before=2025-08-31&concelho=lisboa')
            ->assertOk()
            ->assertJsonPath('data.incidents', 1)
            ->assertJsonPath('data.aerial.value', 2)
            ->assertJsonPath('data.aerial.incident.id', '1001');
    }

    /** @test */
    public function it_ignores_non_numeric_snapshots_and_returns_nulls_without_data(): void
    {
        $this->incident('1001', 'Lisboa', '2025-08-10 14:00');
        $this->history('1001', 60, 14, 2, '2025-08-10 16:00');
        $this->history('1001', '999', '999', '999', '2025-08-10 17:00');

        $this->getJson('v2/stats/means-peak?after=2025-08-01&before=2025-08-31')
            ->assertOk()
            ->assertJsonPath('data.man.value', 60);

        $this->getJson('v2/stats/means-peak?after=2024-01-01&before=2024-01-31')
            ->assertOk()
            ->assertJsonPath('data.incidents', 0)
            ->assertJsonPath('data.man', null)
            ->assertJsonPath('data.terrain', null)
            ->assertJsonPath('data.aerial', null);
    }

    /** @test */
    public function it_handles_legacy_documents_with_a_separate_id_field(): void
    {
        // Pre-v5 documents: ObjectId `_id` plus the business id in `id`, and
        // history referencing it through `id` instead of `incidentId`.
        Incident::raw(fn ($collection) => $collection->insertOne([
            'id' => '2021001',
            'concelho' => 'Lisboa',
            'isFire' => true,
            'active' => false,
            'dateTime' => new UTCDateTime(Carbon::parse('2025-08-05 12:00', 'Europe/Lisbon')),
            'created' => new UTCDateTime(Carbon::parse('2025-08-05 12:00', 'Europe/Lisbon')),
            'updated' => new UTCDateTime(Carbon::parse('2025-08-05 18:00', 'Europe/Lisbon')),
        ]));
        IncidentHistory::raw(fn ($collection) => $collection->insertOne([
            'id' => '2021001',
            'man' => 80,
            'terrain' => 20,
            'aerial' => 4,
            'created' => new UTCDateTime(Carbon::parse('2025-08-05 15:00', 'Europe/Lisbon')),
        ]));

        $this->getJson('v2/stats/means-peak?after=2025-08-01&before=2025-08-31')
            ->assertOk()
            ->assertJsonPath('data.incidents', 1)
            ->assertJsonPath('data.man.value', 80)
            ->assertJsonPath('data.man.at', '2025-08-05T15:00:00+01:00')
            ->assertJsonPath('data.man.incident.id', '2021001');
    }

    private function incident(string $id, string $concelho, string $start): void
    {
        $dateTime = Carbon::parse($start, 'Europe/Lisbon');

        IncidentFactory::new()->fire()->create([
            'id' => $id,
            'concelho' => $concelho,
            'dateTime' => $dateTime,
            'date' => $dateTime->format('d-m-Y'),
            'hour' => $dateTime->format('H:i'),
        ]);
    }

    private function history(string $incidentId, $man, $terrain, $aerial, string $at): void
    {
        $history = new IncidentHistory();
        $history->incidentId = $incidentId;
        $history->man = $man;
        $history->terrain = $terrain;
        $history->aerial = $aerial;
        $history->created = Carbon::parse($at, 'Europe/Lisbon');
        $history->save();
    }
}

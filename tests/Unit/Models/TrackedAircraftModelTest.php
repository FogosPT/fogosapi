<?php

namespace Tests\Unit\Models;

use App\Models\TrackedAircraft;
use Tests\TestCase;

class TrackedAircraftModelTest extends TestCase
{
    private TrackedAircraft $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new TrackedAircraft();
    }

    /** @test */
    public function it_has_correct_collection_set(): void
    {
        self::assertEquals('tracked_aircraft', $this->model->getTable());
    }

    /** @test */
    public function it_uses_mongodb_connection(): void
    {
        self::assertEquals('mongodb', $this->model->getConnectionName());
    }

    /** @test */
    public function it_casts_notify_and_active_to_boolean(): void
    {
        self::assertTrue($this->model->hasCast('notify', 'boolean'));
        self::assertTrue($this->model->hasCast('active', 'boolean'));
    }

    /** @test */
    public function it_has_required_fillable_fields(): void
    {
        $fillable = $this->model->getFillable();
        foreach (['icao', 'registration', 'name', 'type', 'base', 'operator', 'notify', 'active', 'notes'] as $field) {
            self::assertContains($field, $fillable);
        }
    }
}

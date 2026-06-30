<?php

namespace Tests\Unit\Models;

use App\Models\FlightPosition;
use Tests\TestCase;

class FlightPositionModelTest extends TestCase
{
    private FlightPosition $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new FlightPosition();
    }

    /** @test */
    public function it_has_correct_dates_properties_set(): void
    {
        self::assertEquals('created', $this->model::CREATED_AT);
        self::assertEquals('updated', $this->model::UPDATED_AT);
    }

    /** @test */
    public function it_has_correct_primary_key_set(): void
    {
        self::assertEquals('_id', $this->model->getKeyName());
    }

    /** @test */
    public function it_has_correct_collection_set(): void
    {
        self::assertEquals('flight_positions', $this->model->getTable());
    }

    /** @test */
    public function it_uses_mongodb_connection(): void
    {
        self::assertEquals('mongodb', $this->model->getConnectionName());
    }

    /**
     * @test
     *
     * @dataProvider propertiesCastsProvider
     */
    public function it_casts_properties_to_correct_type(string $propertyName, string $cast): void
    {
        self::assertTrue($this->model->hasCast($propertyName, $cast));
    }

    public static function propertiesCastsProvider(): array
    {
        return [
            'lat as float' => ['lat', 'float'],
            'lon as float' => ['lon', 'float'],
            'altitude as integer' => ['altitude', 'integer'],
            'ground_speed as integer' => ['ground_speed', 'integer'],
            'vertical_speed as integer' => ['vertical_speed', 'integer'],
            'track as integer' => ['track', 'integer'],
            'on_ground as boolean' => ['on_ground', 'boolean'],
        ];
    }
}

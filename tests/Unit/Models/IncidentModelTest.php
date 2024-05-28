<?php

namespace Tests\Unit\Models;

use App\Models\Incident;
use Tests\TestCase;

class IncidentModelTest extends TestCase
{
    private Incident $model;

    public function propertiesCastsProvider(): array
    {
        return [
            'active as boolean' => ['active', 'boolean'],
            'aerial as integer' => ['aerial', 'integer'],
            'coords as boolean' => ['coords', 'boolean'],
            'dico as string' => ['dico', 'string'],
            'disappear as boolean' => ['disappear', 'boolean'],
            'id as string' => ['id', 'string'],
            'important as boolean' => ['important', 'boolean'],
            'isFire as boolean' => ['isFire', 'boolean'],
            'isOtherFire as boolean' => ['isOtherFire', 'boolean'],
            'isOtherIncident as boolean' => ['isOtherIncident', 'boolean'],
            'isTransporteFire as boolean' => ['isTransporteFire', 'boolean'],
            'isUrbanFire as boolean' => ['isUrbanFire', 'boolean'],
            'lat as float' => ['lat', 'float'],
            'lng as float' => ['lng', 'float'],
            'man as integer' => ['man', 'integer'],
            'naturezaCode as string' => ['naturezaCode', 'string'],
            'sadoId as string' => ['sadoId', 'string'],
            'sharepointId as integer' => ['sharepointId', 'integer'],
            'statusCode as integer' => ['statusCode', 'integer'],
            'terrain as integer' => ['terrain', 'integer'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new Incident();
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
        self::assertEquals('data', $this->model->getTable());
    }

    /** @test */
    public function it_has_correct_dates_properties_casted(): void
    {
        $dates = $this->model->getDates();

        self::assertContains('dateTime', $dates);
        self::assertContains('updated', $dates);
        self::assertContains('created', $dates);
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
}

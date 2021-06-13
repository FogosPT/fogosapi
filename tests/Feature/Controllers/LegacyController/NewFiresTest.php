<?php

namespace Tests\Feature\Controllers\LegacyController;

use Database\Factories\IncidentFactory;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class NewFiresTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function it_can_list_new_active_fire_incidents(): void
    {
        $this->withoutJobs();

        $fireIncident = IncidentFactory::new()->active()->fire()->create();

        $this->json('GET', 'new/fires')
            ->seeJsonStructure([
                'success',
                'data',
            ])
            ->seeJsonContains([
                'id' => $fireIncident->id,
                'coords' => $fireIncident->coords,
                'dateTime' => $fireIncident->dateTimeObject,
                'date' => $fireIncident->date,
                'hour' => $fireIncident->hour,
                'location' => $fireIncident->location,
                'aerial' => $fireIncident->aerial,
                'terrain' => $fireIncident->terrain,
                'district' => $fireIncident->district,
                'concelho' => $fireIncident->concelho,
                'dico' => $fireIncident->dico,
                'lat' => $fireIncident->lat,
                'lng' => $fireIncident->lng,
                'naturezaCode' => $fireIncident->naturezaCode,
                'natureza' => $fireIncident->natureza,
                'statusCode' => $fireIncident->statusCode,
                'statusColor' => $fireIncident->statusColor,
                'status' => $fireIncident->status,
                'important' => $fireIncident->important,
                'localidade' => $fireIncident->localidade,
                'active' => $fireIncident->active,
                'sadoId' => $fireIncident->sadoId,
                'sharepointId' => $fireIncident->sharepointId,
                'extra' => $fireIncident->extra,
                'disappear' => $fireIncident->disappear,
                'created' => $fireIncident->createdObject,
                'updated' => $fireIncident->updatedObject,
            ])
            ->response
            ->assertJsonCount(1, 'data');
    }
}

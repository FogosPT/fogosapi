<?php

namespace Tests\Feature\Controllers\IncidentController;

use App\Models\IncidentPhoto;
use Carbon\Carbon;
use Database\Factories\IncidentFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    /** @test */
    public function active_pages_are_newest_first_without_overlap_and_expose_the_paginator(): void
    {
        $this->fire('1001', '2025-08-10 10:00', ['active' => true]);
        $this->fire('1002', '2025-08-10 12:00', ['active' => true]);
        $this->fire('1003', '2025-08-10 11:00', ['active' => true]);

        $page1 = $this->getJson('v2/incidents/active?limit=2')
            ->assertOk()
            ->assertJsonPath('paginator', [
                'currentPage' => 1,
                'totalPages' => 2,
                'totalItems' => 3,
                'perPage' => 2,
            ]);
        $this->assertSame(['1002', '1003'], array_column($page1->json('data'), 'id'));

        $page2 = $this->getJson('v2/incidents/active?limit=2&page=2')
            ->assertOk()
            ->assertJsonPath('paginator.currentPage', 2);
        $this->assertSame(['1001'], array_column($page2->json('data'), 'id'));

        $this->getJson('v2/incidents/active?limit=2&page=3')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function limit_is_clamped_and_falls_back_to_the_default(): void
    {
        $this->fire('1001', '2025-08-10 10:00', ['active' => true]);

        $this->getJson('v2/incidents/active?limit=5000')->assertJsonPath('paginator.perPage', 1000);
        $this->getJson('v2/incidents/active?limit=0')->assertJsonPath('paginator.perPage', 1);
        $this->getJson('v2/incidents/active?limit=-5')->assertJsonPath('paginator.perPage', 1);
        $this->getJson('v2/incidents/active?limit=abc')->assertJsonPath('paginator.perPage', 1000);
        $this->getJson('v2/incidents/active')->assertJsonPath('paginator.perPage', 1000);

        $this->getJson('v2/incidents/search?limit=5000')->assertJsonPath('paginator.perPage', 1000);
        $this->getJson('v2/incidents/search')->assertJsonPath('paginator.perPage', 50);
        $this->getJson('v2/incidents/1000ha-burned')->assertJsonPath('paginator.perPage', 500);
    }

    /** @test */
    public function search_pages_are_ordered_by_start_time_desc(): void
    {
        $this->fire('1001', '2025-08-10 10:00');
        $this->fire('1002', '2025-08-12 10:00');
        $this->fire('1003', '2025-08-11 10:00');

        $page1 = $this->getJson('v2/incidents/search?after=2025-08-01&before=2025-08-31&limit=2')
            ->assertOk()
            ->assertJsonPath('paginator.totalItems', 3)
            ->assertJsonPath('paginator.totalPages', 2);
        $page2 = $this->getJson('v2/incidents/search?after=2025-08-01&before=2025-08-31&limit=2&page=2')
            ->assertOk();

        $this->assertSame(
            ['1002', '1003', '1001'],
            array_merge(array_column($page1->json('data'), 'id'), array_column($page2->json('data'), 'id'))
        );
    }

    /** @test */
    public function burned_more_than_1000ha_is_ordered_by_start_time_desc(): void
    {
        $this->fire('1001', '2025-07-01 10:00', ['icnf' => ['burnArea' => ['total' => 1500]]]);
        $this->fire('1002', '2025-08-01 10:00', ['icnf' => ['burnArea' => ['total' => 2500]]]);
        $this->fire('1003', '2025-08-02 10:00', ['icnf' => ['burnArea' => ['total' => 10]]]);

        $response = $this->getJson('v2/incidents/1000ha-burned?limit=1')
            ->assertOk()
            ->assertJsonPath('paginator.totalItems', 2)
            ->assertJsonPath('paginator.totalPages', 2);
        $this->assertSame(['1002'], array_column($response->json('data'), 'id'));

        $response = $this->getJson('v2/incidents/1000ha-burned?limit=1&page=2')->assertOk();
        $this->assertSame(['1001'], array_column($response->json('data'), 'id'));
    }

    /** @test */
    public function photo_listings_expose_the_last_page(): void
    {
        foreach (range(1, 3) as $i) {
            IncidentPhoto::create([
                'fire_id' => '1001',
                'status' => IncidentPhoto::STATUS_APPROVED,
                'public' => true,
                'storage_key' => "photos/{$i}.jpg",
            ]);
        }

        $this->getJson('v2/photos/latest?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta', [
                'total' => 3,
                'page' => 1,
                'per_page' => 2,
                'last_page' => 2,
            ]);
    }

    private function fire(string $id, string $start, array $attributes = []): void
    {
        $dateTime = Carbon::parse($start, 'Europe/Lisbon');

        IncidentFactory::new()->fire()->create(array_merge([
            'id' => $id,
            'dateTime' => $dateTime,
            'date' => $dateTime->format('d-m-Y'),
            'hour' => $dateTime->format('H:i'),
        ], $attributes));
    }
}

<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessICNFNewFireData;
use App\Models\Incident;
use Carbon\Carbon;
use Database\Factories\IncidentFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use MongoDB\BSON\UTCDateTime;
use Tests\TestCase;

class ProcessICNFNewFireDataBatchTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function existing_incidents_are_loaded_in_batch_and_save_only_changes(): void
    {
        Queue::fake();

        IncidentFactory::new()->fire()->create([
            'id' => '2026100001',
            'active' => false,
            'statusCode' => 5,
            'kml' => 'big kml body',
        ]);

        // Legacy document: ObjectId _id plus the business id in `id`.
        Incident::raw(fn ($collection) => $collection->insertOne([
            'id' => '2021000001',
            'isFire' => true,
            'active' => true,
            'statusCode' => 5,
            'dateTime' => new UTCDateTime(Carbon::parse('2021-08-01')),
        ]));

        $job = new ProcessICNFNewFireData();
        $load = new \ReflectionMethod($job, 'loadExisting');
        $existing = $load->invoke($job, ['2026100001', '2021000001', '999']);

        // PHP turns numeric-string keys into ints; lookups by string still work.
        $this->assertSame(['2026100001', '2021000001'], array_map('strval', array_keys($existing)));

        $incident = $existing['2026100001'];
        $this->assertSame('2026100001', (string) $incident->id);
        $this->assertNull($incident->kml, 'kml is not loaded');

        $incident->active = true;
        $incident->save();

        $fresh = Incident::whereFireId('2026100001')->first();
        $this->assertTrue($fresh->active);
        $this->assertSame('big kml body', $fresh->kml, 'unloaded fields are kept');

        $legacy = $existing['2021000001'];
        $legacy->active = false;
        $legacy->save();

        $this->assertFalse(Incident::whereFireId('2021000001')->first()->active);
    }
}

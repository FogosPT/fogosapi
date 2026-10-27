<?php

namespace Tests\Feature\Controllers;

use App\Jobs\BroadcastApprovedPhoto;
use App\Models\IncidentPhoto;
use Database\Factories\IncidentFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;
use Tests\TestCase;

class PhotoModerationApproveTest extends TestCase
{
    use DatabaseMigrations;

    private ?string $keyBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        IncidentFactory::new()->active()->fire()->create(['id' => '2026300001', 'dateTime' => now()]);

        $this->keyBackup = $_SERVER['PHOTO_MODERATION_KEY'] ?? null;
        $_SERVER['PHOTO_MODERATION_KEY'] = $_ENV['PHOTO_MODERATION_KEY'] = 'mod-key';
        putenv('PHOTO_MODERATION_KEY=mod-key');
    }

    protected function tearDown(): void
    {
        $value = $this->keyBackup ?? '';
        $_SERVER['PHOTO_MODERATION_KEY'] = $_ENV['PHOTO_MODERATION_KEY'] = $value;
        putenv("PHOTO_MODERATION_KEY={$value}");

        parent::tearDown();
    }

    /** @test */
    public function repeated_approvals_with_publish_broadcast_once(): void
    {
        $photo = $this->pendingPhoto();

        foreach (['queued', 'already', 'already'] as $expected) {
            $this->approve($photo, ['publish' => true, 'twitter' => true])
                ->assertOk()
                ->assertJson(['success' => true, 'published' => $expected]);
        }

        Queue::assertPushed(BroadcastApprovedPhoto::class, 1);
        Queue::assertPushed(BroadcastApprovedPhoto::class, fn ($job) => $job->photoId === (string) $photo->_id && $job->twitter);

        $fresh = IncidentPhoto::find($photo->_id);
        $this->assertSame(IncidentPhoto::STATUS_APPROVED, $fresh->status);
        $this->assertNotNull($fresh->moderation['reviewed_at'] ?? null);
        $this->assertNotNull($fresh->moderation['published_at'] ?? null);
    }

    /** @test */
    public function approval_without_publish_does_not_broadcast(): void
    {
        $photo = $this->pendingPhoto();

        $this->approve($photo, [])->assertOk()->assertJson(['published' => false]);

        Queue::assertNotPushed(BroadcastApprovedPhoto::class);
        $this->assertSame(IncidentPhoto::STATUS_APPROVED, IncidentPhoto::find($photo->_id)->status);

        // Publishing later is still possible.
        $this->approve($photo, ['publish' => true])->assertJson(['published' => 'queued']);
        Queue::assertPushed(BroadcastApprovedPhoto::class, 1);
    }

    /** @test */
    public function a_failed_enqueue_releases_the_claim_so_a_retry_publishes(): void
    {
        $photo = $this->pendingPhoto();

        Queue::swap(new class(app()) extends QueueFake {
            public function push($job, $data = '', $queue = null)
            {
                throw new \RuntimeException('Redis is loading the dataset in memory');
            }
        });

        $this->approve($photo, ['publish' => true])
            ->assertStatus(503)
            ->assertJson(['success' => false, 'error' => 'publish_queue_unavailable']);
        $this->assertNull(IncidentPhoto::find($photo->_id)->moderation['published_at'] ?? null);

        // Queue back: the retry publishes.
        Queue::fake();
        $this->approve($photo, ['publish' => true])->assertJson(['published' => 'queued']);
        Queue::assertPushed(BroadcastApprovedPhoto::class, 1);
    }

    /** @test */
    public function publish_is_skipped_when_the_incident_does_not_exist(): void
    {
        $photo = $this->pendingPhoto('2026999999');

        $this->approve($photo, ['publish' => true])->assertJson(['published' => false]);
        Queue::assertNotPushed(BroadcastApprovedPhoto::class);
    }

    /** @test */
    public function the_broadcast_job_survives_unavailable_storage_and_disabled_channels(): void
    {
        $photo = $this->pendingPhoto();

        // MinIO is not reachable in tests and every channel flag is off:
        // each failure is caught, nothing escapes the job.
        (new BroadcastApprovedPhoto((string) $photo->_id, true))->handle();

        $this->assertTrue(true);
    }

    private function pendingPhoto(string $fireId = '2026300001'): IncidentPhoto
    {
        $photo = new IncidentPhoto();
        $photo->fire_id = $fireId;
        $photo->status = IncidentPhoto::STATUS_PENDING;
        $photo->public = true;
        $photo->storage_key = 'incidents/2026300001/x.jpg';
        $photo->moderation = ['reviewed_at' => null, 'reason' => null];
        $photo->save();

        return $photo;
    }

    private function approve(IncidentPhoto $photo, array $body)
    {
        return $this->postJson("v2/moderation/photos/{$photo->_id}/approve", $body, ['key' => 'mod-key']);
    }
}

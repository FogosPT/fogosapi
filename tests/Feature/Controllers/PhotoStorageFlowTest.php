<?php

namespace Tests\Feature\Controllers;

use App\Jobs\BroadcastApprovedPhoto;
use App\Models\IncidentPhoto;
use App\Resources\IncidentPhotoModerationResource;
use App\Tools\PhotoStorageTool;
use Carbon\Carbon;
use Database\Factories\IncidentFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Photos as plain files: pending/ until approved, incidents/ (public, cached
 * for a year on cdn.fogos.pt) after; deleted public photos are purged from
 * Cloudflare; moderators see pending ones through a signed URL.
 */
class PhotoStorageFlowTest extends TestCase
{
    use DatabaseMigrations;

    private const FIRE = '2026300001';

    private ?string $keyBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake(PhotoStorageTool::DISK);
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);
        config([
            'filesystems.disks.photos.url' => 'https://cdn.fogos.pt/incident-photos',
            'services.cloudflare.zone_id' => 'zone123',
            'services.cloudflare.cache_purge_token' => 'cf-test-token-abcdef',
            'services.photos.api_url' => 'https://api.fogos.pt',
        ]);

        IncidentFactory::new()->active()->fire()->create(['id' => self::FIRE, 'dateTime' => now()]);

        $this->keyBackup = $_SERVER['PHOTO_MODERATION_KEY'] ?? null;
        $_SERVER['PHOTO_MODERATION_KEY'] = $_ENV['PHOTO_MODERATION_KEY'] = 'mod-key';
        putenv('PHOTO_MODERATION_KEY=mod-key');
    }

    protected function tearDown(): void
    {
        $value = $this->keyBackup ?? '';
        $_SERVER['PHOTO_MODERATION_KEY'] = $_ENV['PHOTO_MODERATION_KEY'] = $value;
        putenv("PHOTO_MODERATION_KEY={$value}");
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function photo(string $status, string $key, bool $withFile = true): IncidentPhoto
    {
        $photo = new IncidentPhoto();
        $photo->fire_id = self::FIRE;
        $photo->status = $status;
        $photo->public = true;
        $photo->storage_key = $key;
        $photo->moderation = ['reviewed_at' => null, 'reason' => null];
        $photo->save();

        if ($withFile) {
            Storage::disk(PhotoStorageTool::DISK)->put($key, 'jpeg-bytes-' . $key);
        }

        return $photo;
    }

    /** @test */
    public function uploads_are_stored_under_pending(): void
    {
        $key = PhotoStorageTool::pendingKey(self::FIRE, 'abc');
        PhotoStorageTool::store('bytes', $key);

        $this->assertSame('pending/' . self::FIRE . '/abc.jpg', $key);
        Storage::disk(PhotoStorageTool::DISK)->assertExists($key);
        $this->assertSame('incidents/' . self::FIRE . '/abc.jpg', PhotoStorageTool::publicKey($key));
    }

    /** @test */
    public function approving_moves_the_file_to_the_public_path_before_broadcasting(): void
    {
        $photo = $this->photo(IncidentPhoto::STATUS_PENDING, 'pending/' . self::FIRE . '/p1.jpg');

        $this->postJson("v2/moderation/photos/{$photo->_id}/approve", ['publish' => true], ['key' => 'mod-key'])
            ->assertOk()->assertJson(['published' => 'queued']);

        $disk = Storage::disk(PhotoStorageTool::DISK);
        $disk->assertMissing('pending/' . self::FIRE . '/p1.jpg');
        $disk->assertExists('incidents/' . self::FIRE . '/p1.jpg');
        $fresh = IncidentPhoto::find($photo->_id);
        $this->assertSame(IncidentPhoto::STATUS_APPROVED, $fresh->status);
        $this->assertSame('incidents/' . self::FIRE . '/p1.jpg', $fresh->storage_key);
        Queue::assertPushed(BroadcastApprovedPhoto::class, 1);

        // Approving again keeps the public key.
        $this->postJson("v2/moderation/photos/{$photo->_id}/approve", [], ['key' => 'mod-key'])->assertOk();
        $this->assertSame('incidents/' . self::FIRE . '/p1.jpg', IncidentPhoto::find($photo->_id)->storage_key);
    }

    /** @test */
    public function approving_a_pending_photo_without_its_file_fails_without_broadcast(): void
    {
        $photo = $this->photo(IncidentPhoto::STATUS_PENDING, 'pending/' . self::FIRE . '/gone.jpg', false);

        $this->postJson("v2/moderation/photos/{$photo->_id}/approve", ['publish' => true], ['key' => 'mod-key'])
            ->assertStatus(409)->assertJson(['error' => 'photo_file_missing']);

        $this->assertSame(IncidentPhoto::STATUS_PENDING, IncidentPhoto::find($photo->_id)->status);
        Queue::assertNotPushed(BroadcastApprovedPhoto::class);
    }

    /** @test */
    public function rejecting_deletes_the_pending_file_without_purging(): void
    {
        $photo = $this->photo(IncidentPhoto::STATUS_PENDING, 'pending/' . self::FIRE . '/r1.jpg');

        $this->postJson("v2/moderation/photos/{$photo->_id}/reject", [], ['key' => 'mod-key'])->assertOk();

        Storage::disk(PhotoStorageTool::DISK)->assertMissing('pending/' . self::FIRE . '/r1.jpg');
        $this->assertNull(IncidentPhoto::find($photo->_id));
        Http::assertNothingSent();
    }

    /** @test */
    public function deleting_an_approved_photo_purges_its_public_url(): void
    {
        $photo = $this->photo(IncidentPhoto::STATUS_APPROVED, 'incidents/' . self::FIRE . '/d1.jpg');

        $this->postJson("v2/photos/{$photo->_id}/delete", [], ['key' => 'mod-key'])->assertOk();

        Storage::disk(PhotoStorageTool::DISK)->assertMissing('incidents/' . self::FIRE . '/d1.jpg');
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone123/purge_cache'
            && $request->hasHeader('Authorization', 'Bearer cf-test-token-abcdef')
            && $request['files'] === ['https://cdn.fogos.pt/incident-photos/incidents/' . self::FIRE . '/d1.jpg']);
    }

    /** @test */
    public function without_a_token_deleting_only_logs(): void
    {
        config(['services.cloudflare.cache_purge_token' => null]);
        $photo = $this->photo(IncidentPhoto::STATUS_APPROVED, 'incidents/' . self::FIRE . '/d2.jpg');

        $this->postJson("v2/photos/{$photo->_id}/delete", [], ['key' => 'mod-key'])->assertOk();

        Http::assertNothingSent();
        $this->assertNull(IncidentPhoto::find($photo->_id));
    }

    /** @test */
    public function moderators_get_a_signed_url_for_pending_photos_and_the_cdn_url_for_approved_ones(): void
    {
        $pending = $this->photo(IncidentPhoto::STATUS_PENDING, 'pending/' . self::FIRE . '/m1.jpg');
        $approved = $this->photo(IncidentPhoto::STATUS_APPROVED, 'incidents/' . self::FIRE . '/m2.jpg');

        $approvedUrl = (new IncidentPhotoModerationResource($approved))->resolve()['url'];
        $this->assertSame('https://cdn.fogos.pt/incident-photos/incidents/' . self::FIRE . '/m2.jpg', $approvedUrl);

        $url = (new IncidentPhotoModerationResource($pending))->resolve()['url'];
        $this->assertStringStartsWith("https://api.fogos.pt/v2/moderation/photos/{$pending->_id}/image?expires=", $url);
        $path = substr($url, strlen('https://api.fogos.pt'));

        $response = $this->get($path)->assertOk();
        $this->assertSame('jpeg-bytes-pending/' . self::FIRE . '/m1.jpg', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));

        // Without (or with a tampered) signature: refused.
        $this->get("/v2/moderation/photos/{$pending->_id}/image")->assertForbidden();
        $this->get(str_replace((string) $pending->_id, (string) $approved->_id, $path))->assertForbidden();

        // Expired.
        Carbon::setTestNow(now()->addMinutes(121));
        $this->get($path)->assertForbidden();
    }

    /** @test */
    public function the_broadcast_job_reads_the_photo_from_the_photos_disk(): void
    {
        $photo = $this->photo(IncidentPhoto::STATUS_APPROVED, 'incidents/' . self::FIRE . '/b1.jpg');

        $this->assertSame('jpeg-bytes-incidents/' . self::FIRE . '/b1.jpg', PhotoStorageTool::read($photo->storage_key));
        // Every channel flag is off in tests: the job must just run.
        (new BroadcastApprovedPhoto((string) $photo->_id, false))->handle();
        $this->assertTrue(true);
    }
}

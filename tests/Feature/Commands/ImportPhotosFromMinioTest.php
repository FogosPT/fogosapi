<?php

namespace Tests\Feature\Commands;

use App\Models\IncidentPhoto;
use App\Tools\PhotoStorageTool;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportPhotosFromMinioTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('minio');
        Storage::fake(PhotoStorageTool::DISK);
    }

    private function row(string $status, string $key): IncidentPhoto
    {
        $photo = new IncidentPhoto();
        $photo->fire_id = '2026300001';
        $photo->status = $status;
        $photo->storage_key = $key;
        $photo->save();

        return $photo;
    }

    /** @test */
    public function it_copies_approved_as_they_are_and_pending_to_pending_with_the_key_updated(): void
    {
        $minio = Storage::disk('minio');
        $minio->put('incidents/2026300001/a.jpg', 'approved-bytes');
        $minio->put('incidents/2026300001/p.jpg', 'pending-bytes');
        $minio->put('incidents/2026300001/orphan.jpg', 'orphan-bytes');
        $approved = $this->row(IncidentPhoto::STATUS_APPROVED, 'incidents/2026300001/a.jpg');
        $pending = $this->row(IncidentPhoto::STATUS_PENDING, 'incidents/2026300001/p.jpg');
        $this->row(IncidentPhoto::STATUS_APPROVED, 'incidents/2026300001/missing.jpg');
        $alreadyNew = $this->row(IncidentPhoto::STATUS_PENDING, 'pending/2026300001/new.jpg');

        $this->artisan('photos:import-from-minio')
            ->expectsOutputToContain('Copied 2; 0 already there; 1 pending moved to pending/; 1 rows without an object in MinIO; 0 md5 mismatches.')
            ->expectsOutputToContain('1 object(s) in MinIO without a photo row (not copied): incidents/2026300001/orphan.jpg')
            ->assertSuccessful();

        $photos = Storage::disk(PhotoStorageTool::DISK);
        $this->assertSame('approved-bytes', $photos->get('incidents/2026300001/a.jpg'));
        $this->assertSame('pending-bytes', $photos->get('pending/2026300001/p.jpg'));
        $photos->assertMissing('incidents/2026300001/p.jpg');
        $photos->assertMissing('incidents/2026300001/orphan.jpg');
        $this->assertSame('incidents/2026300001/a.jpg', IncidentPhoto::find($approved->_id)->storage_key);
        $this->assertSame('pending/2026300001/p.jpg', IncidentPhoto::find($pending->_id)->storage_key);
        $this->assertSame('pending/2026300001/new.jpg', IncidentPhoto::find($alreadyNew->_id)->storage_key);

        // Second run: nothing left to copy (the pending row now has its new key).
        $this->artisan('photos:import-from-minio')
            ->expectsOutputToContain('Copied 0; 1 already there; 0 pending moved to pending/')
            ->assertSuccessful();
    }

    /** @test */
    public function dry_run_writes_nothing(): void
    {
        Storage::disk('minio')->put('incidents/2026300001/p.jpg', 'pending-bytes');
        $pending = $this->row(IncidentPhoto::STATUS_PENDING, 'incidents/2026300001/p.jpg');

        $this->artisan('photos:import-from-minio', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run] would copy 1;')
            ->assertSuccessful();

        Storage::disk(PhotoStorageTool::DISK)->assertMissing('pending/2026300001/p.jpg');
        $this->assertSame('incidents/2026300001/p.jpg', IncidentPhoto::find($pending->_id)->storage_key);
    }
}

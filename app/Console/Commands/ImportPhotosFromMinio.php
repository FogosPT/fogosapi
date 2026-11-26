<?php

namespace App\Console\Commands;

use App\Models\IncidentPhoto;
use App\Tools\PhotoStorageTool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-shot migration of the incident photos from MinIO (S3 disk `minio`) to
 * plain files on the `photos` disk. MinIO stores objects in its own format
 * (a directory with xl.meta + parts per object), so they are read through the
 * S3 API, not copied from its data directory.
 *
 * Approved photos keep their key (incidents/…, the public URL does not
 * change); pending ones go to pending/… and their storage_key is updated.
 * Every copy is checked by md5; a file already there with the same md5 is
 * skipped, so the command can run again (before a final cut-over, or after an
 * interruption). Objects in the bucket without a photo row are only reported.
 *
 * Temporary: removed together with the `minio` disk once the migration is done.
 */
class ImportPhotosFromMinio extends Command
{
    protected $signature = 'photos:import-from-minio {--dry-run : Report what would be copied without writing}';

    protected $description = 'Copy the incident photos from MinIO to the local photos disk (pending ones to pending/)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $source = Storage::disk('minio');
        $target = Storage::disk(PhotoStorageTool::DISK);

        $copied = $already = $missing = $mismatch = $rekeyed = 0;
        $missingIds = [];
        $known = [];

        $photos = IncidentPhoto::whereNotNull('storage_key')->cursor();
        foreach ($photos as $photo) {
            $key = (string) $photo->storage_key;
            if ($key === '') {
                continue;
            }

            // Uploaded after the switch: already on the photos disk.
            if (PhotoStorageTool::isPending($key)) {
                $known[PhotoStorageTool::publicKey($key)] = true;
                continue;
            }
            $known[$key] = true;

            $targetKey = $photo->status === IncidentPhoto::STATUS_APPROVED
                ? $key
                : PhotoStorageTool::PENDING_PREFIX . substr($key, strlen(PhotoStorageTool::PUBLIC_PREFIX));

            if (!$source->exists($key)) {
                $missing++;
                $missingIds[] = (string) $photo->_id;
                continue;
            }

            $bytes = $source->get($key);
            $md5 = md5($bytes);

            if ($target->exists($targetKey) && md5($target->get($targetKey)) === $md5) {
                $already++;
            } elseif ($dryRun) {
                $copied++;
            } else {
                $target->put($targetKey, $bytes, ['visibility' => 'public']);
                if (md5($target->get($targetKey)) !== $md5) {
                    $mismatch++;
                    $this->error("md5 mismatch after copy: {$targetKey}");
                    continue;
                }
                $copied++;
            }

            if ($targetKey !== $key) {
                $rekeyed++;
                if (!$dryRun) {
                    IncidentPhoto::where('_id', $photo->getKey())->update(['storage_key' => $targetKey]);
                }
            }
        }

        $orphans = array_values(array_filter(
            $source->allFiles(),
            fn (string $object) => !isset($known[$object])
        ));

        $prefix = $dryRun ? '[dry-run] would copy' : 'Copied';
        $this->info("{$prefix} {$copied}; {$already} already there; {$rekeyed} pending moved to pending/; {$missing} rows without an object in MinIO; {$mismatch} md5 mismatches.");
        if ($missingIds !== []) {
            $this->warn('Rows without an object: ' . implode(', ', array_slice($missingIds, 0, 20)) . (count($missingIds) > 20 ? ' …' : ''));
        }
        if ($orphans !== []) {
            $this->warn(count($orphans) . ' object(s) in MinIO without a photo row (not copied): ' . implode(', ', array_slice($orphans, 0, 20)) . (count($orphans) > 20 ? ' …' : ''));
        }

        return $mismatch > 0 ? self::FAILURE : self::SUCCESS;
    }
}

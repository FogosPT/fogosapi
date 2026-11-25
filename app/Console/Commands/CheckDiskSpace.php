<?php

namespace App\Console\Commands;

use App\Tools\DiscordTool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Posts on the Discord errors channel when the disk holding the app (the host
 * disk, through the bind mount) goes past a usage level, once per level, and
 * once when it is back below the first level minus a margin. Scheduled as a
 * command in the scheduler container, not as a queued job: a full disk takes
 * Redis, and with it the queue, down first.
 */
class CheckDiskSpace extends Command
{
    protected $signature = 'system:disk-check {--path= : Directory on the disk to check (default: the app)}';

    protected $description = 'Alert on Discord when the disk usage passes 85 % / 95 %';

    /** Usage levels (%) that trigger an alert, ascending. */
    public const LEVELS = [85, 95];

    /** Recovery is announced below the first level minus this margin. */
    public const RECOVERY_MARGIN = 5;

    public const CACHE_KEY = 'system:disk:announced-level';

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path();
        [$total, $free] = $this->measure($path);

        if (!$total || $free === false) {
            $this->error("Cannot read disk space of {$path}");

            return self::FAILURE;
        }

        $used = round(100 * ($total - $free) / $total, 1);
        $level = 0;
        foreach (self::LEVELS as $threshold) {
            if ($used >= $threshold) {
                $level = $threshold;
            }
        }

        $announced = (int) Cache::get(self::CACHE_KEY, 0);
        $summary = self::format($used) . ' % usado, ' . self::gb($free) . ' GB livres de ' . self::gb($total) . ' GB';

        if ($level > $announced) {
            $this->post(($level >= max(self::LEVELS) ? '🚨' : '⚠️') . " Disco do servidor: {$summary}");
            Cache::forever(self::CACHE_KEY, $level);
        } elseif ($announced > 0 && $used < self::LEVELS[0] - self::RECOVERY_MARGIN) {
            $this->post("✅ Disco do servidor voltou ao normal: {$summary}");
            Cache::forget(self::CACHE_KEY);
        }

        $this->info($summary);

        return self::SUCCESS;
    }

    /** @return array{0: float|false, 1: float|false} total and free bytes */
    protected function measure(string $path): array
    {
        return [@disk_total_space($path), @disk_free_space($path)];
    }

    protected function post(string $message): void
    {
        DiscordTool::postError($message);
    }

    private static function gb(float $bytes): string
    {
        return self::format($bytes / 1024 ** 3);
    }

    private static function format(float $value): string
    {
        return number_format($value, 1, ',', '');
    }
}

<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\CheckDiskSpace;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CheckDiskSpaceTest extends TestCase
{
    private const GB = 1024 ** 3;

    private FakeDiskCheck $command;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->command = new FakeDiskCheck();
        $this->app->instance(CheckDiskSpace::class, $this->command);
    }

    private function runAt(float $usedPercent): void
    {
        $this->command->total = 100 * self::GB;
        $this->command->free = (100 - $usedPercent) * self::GB;
        $this->artisan('system:disk-check')->assertSuccessful();
    }

    /** @test */
    public function it_alerts_once_per_level_and_once_on_recovery(): void
    {
        $this->runAt(70);
        $this->assertSame([], $this->command->posted);

        $this->runAt(86);
        $this->runAt(90);
        $this->assertCount(1, $this->command->posted);
        $this->assertStringContainsString('⚠️ Disco do servidor: 86,0 % usado, 14,0 GB livres de 100,0 GB', $this->command->posted[0]);

        $this->runAt(96);
        $this->assertCount(2, $this->command->posted);
        $this->assertStringStartsWith('🚨', $this->command->posted[1]);

        // Below 85 % but inside the margin: no recovery yet.
        $this->runAt(82);
        $this->assertCount(2, $this->command->posted);

        $this->runAt(60);
        $this->assertCount(3, $this->command->posted);
        $this->assertStringStartsWith('✅ Disco do servidor voltou ao normal: 60,0 %', $this->command->posted[2]);
        $this->assertNull(Cache::get(CheckDiskSpace::CACHE_KEY));

        $this->runAt(60);
        $this->assertCount(3, $this->command->posted);
    }

    /** @test */
    public function it_jumps_straight_to_the_critical_level(): void
    {
        $this->runAt(99);

        $this->assertCount(1, $this->command->posted);
        $this->assertStringStartsWith('🚨', $this->command->posted[0]);
        $this->assertSame(95, Cache::get(CheckDiskSpace::CACHE_KEY));
    }

    /** @test */
    public function it_fails_when_the_disk_cannot_be_read(): void
    {
        $this->command->total = false;
        $this->command->free = false;

        $this->artisan('system:disk-check')->assertFailed();
        $this->assertSame([], $this->command->posted);
    }

    /** @test */
    public function it_reads_the_real_disk(): void
    {
        $this->app->forgetInstance(CheckDiskSpace::class);

        $this->artisan('system:disk-check', ['--path' => sys_get_temp_dir()])
            ->expectsOutputToContain('% usado')
            ->assertSuccessful();
    }
}

class FakeDiskCheck extends CheckDiskSpace
{
    public float|false $total = 0;

    public float|false $free = 0;

    /** @var list<string> */
    public array $posted = [];

    protected function measure(string $path): array
    {
        return [$this->total, $this->free];
    }

    protected function post(string $message): void
    {
        $this->posted[] = $message;
    }
}

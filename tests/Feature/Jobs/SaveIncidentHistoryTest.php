<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SaveIncidentHistory;
use App\Jobs\SendFcmNotification;
use App\Models\Incident;
use App\Models\IncidentHistory;
use Database\Factories\IncidentFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SaveIncidentHistoryTest extends TestCase
{
    use DatabaseMigrations;

    private ?string $notificationsBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Pushes are only queued (and faked here) when notifications are on.
        $this->notificationsBackup = $_SERVER['NOTIFICATIONS_ENABLE'] ?? null;
        $_SERVER['NOTIFICATIONS_ENABLE'] = $_ENV['NOTIFICATIONS_ENABLE'] = 'true';
        putenv('NOTIFICATIONS_ENABLE=true');
    }

    protected function tearDown(): void
    {
        $value = $this->notificationsBackup ?? 'false';
        $_SERVER['NOTIFICATIONS_ENABLE'] = $_ENV['NOTIFICATIONS_ENABLE'] = $value;
        putenv("NOTIFICATIONS_ENABLE={$value}");

        parent::tearDown();
    }

    /** @test */
    public function posit_and_cos_changes_are_announced_once(): void
    {
        $incident = $this->incident(['extra' => 'POSIT antigo', 'cos' => 'COS antigo']);

        // Snapshot from before POSIT/COS were tracked: baseline only.
        $this->legacySnapshot($incident);
        $this->runJob($incident);
        $this->assertSame(2, IncidentHistory::count());
        $this->assertNoPushContaining('ponto de situação');

        // New POSIT: announced and recorded.
        $incident->extra = 'POSIT 1';
        $incident->saveQuietly();
        $this->runJob($incident);
        $this->assertPushContaining('Novo ponto de situação: POSIT 1');
        $this->assertSame(3, IncidentHistory::count());

        // Same POSIT on the next run: nothing new.
        Queue::fake();
        $this->runJob($incident);
        $this->assertNoPushContaining('ponto de situação');
        $this->assertSame(3, IncidentHistory::count());

        // New COS.
        $incident->cos = 'COS novo';
        $incident->saveQuietly();
        $this->runJob($incident);
        $this->assertPushContaining('Novo Comandante de Operações de socorro: COS novo');
    }

    /** @test */
    public function clearing_the_posit_is_not_announced(): void
    {
        $incident = $this->incident(['extra' => 'POSIT 1', 'cos' => '']);
        $this->runJob($incident); // first snapshot

        $incident->extra = '';
        $incident->saveQuietly();
        $this->runJob($incident);

        $this->assertNoPushContaining('ponto de situação');
    }

    private function incident(array $attributes): Incident
    {
        return IncidentFactory::new()->active()->fire()->create($attributes + [
            'id' => '2026200001',
            'dateTime' => now(),
            'man' => 10,
            'terrain' => 3,
            'aerial' => 0,
        ]);
    }

    private function legacySnapshot(Incident $incident): void
    {
        IncidentHistory::create([
            'incidentId' => $incident->id,
            'man' => $incident->man,
            'terrain' => $incident->terrain,
            'aerial' => $incident->aerial,
        ]);
    }

    private function runJob(Incident $incident): void
    {
        (new SaveIncidentHistory($incident))->handle();
    }

    private function pushBodies(): array
    {
        return Queue::pushed(SendFcmNotification::class)
            ->map(fn ($job) => $job->message['notification']['body'] ?? '')
            ->all();
    }

    private function assertPushContaining(string $text): void
    {
        $this->assertNotEmpty(
            array_filter($this->pushBodies(), fn ($body) => str_contains($body, $text)),
            "No push containing '{$text}'"
        );
    }

    private function assertNoPushContaining(string $text): void
    {
        $this->assertEmpty(array_filter($this->pushBodies(), fn ($body) => str_contains($body, $text)));
    }
}

<?php

namespace Tests\Feature\Jobs;

use App\Jobs\HandleWeatherWarnings;
use App\Models\WeatherWarning;
use App\Models\WeatherWarningEvent;
use App\Support\WeatherWarningDiff;
use App\Support\WeatherWarningEventRecorder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class WeatherWarningEventsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Carbon::setTestNow('2026-11-10T08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function first_sight_is_emitido(): void
    {
        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00')]);

        $events = $this->events();
        $this->assertSame(['emitido'], $events->pluck('kind')->all());
        $e = $events->first();
        $this->assertSame('STB', $e->district);
        $this->assertSame('Vento', $e->type);
        $this->assertNull($e->fromLevel);
        $this->assertSame('yellow', $e->toLevel);
        $this->assertSame('2026-11-10 07:00', $e->occurredAt->format('Y-m-d H:i'));
        $this->assertSame('2026-11-10 08:00', $e->detectedAt->format('Y-m-d H:i'));
    }

    /** @test */
    public function level_up_is_one_agravado_event(): void
    {
        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T12:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00')]);
        $this->sync([$this->item('STB', 'Vento', 'orange', '2026-11-10T12:00:00', '2026-11-10T21:00:00', '2026-11-10T07:30:00')]);

        $events = $this->events()->slice(1)->values();
        $this->assertSame(['agravado'], $events->pluck('kind')->all());
        $this->assertSame('yellow', $events[0]->fromLevel);
        $this->assertSame('orange', $events[0]->toLevel);
        $this->assertSame('2026-11-10 18:00', $events[0]->previousEndTime->format('Y-m-d H:i'));
        $this->assertSame('2026-11-10 21:00', $events[0]->endTime->format('Y-m-d H:i'));
    }

    /** @test */
    public function level_down_is_desagravado(): void
    {
        $this->sync([$this->item('STB', 'Vento', 'red', '2026-11-10T12:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00')]);
        $this->sync([$this->item('STB', 'Vento', 'orange', '2026-11-10T12:00:00', '2026-11-10T18:00:00', '2026-11-10T07:30:00')]);

        $e = $this->events()->last();
        $this->assertSame('desagravado', $e->kind);
        $this->assertSame('red', $e->fromLevel);
        $this->assertSame('orange', $e->toLevel);
    }

    /** @test */
    public function republish_moving_start_only_records_nothing(): void
    {
        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00')]);
        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T10:12:00', '2026-11-10T18:00:00', '2026-11-10T10:12:00')]);

        $this->assertSame(['emitido'], $this->events()->pluck('kind')->all());
    }

    /** @test */
    public function extended_end_is_prolongado(): void
    {
        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00')]);
        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-11T06:00:00', '2026-11-10T07:30:00')]);

        $e = $this->events()->last();
        $this->assertSame('prolongado', $e->kind);
        $this->assertSame('yellow', $e->fromLevel);
        $this->assertSame('yellow', $e->toLevel);
        $this->assertSame('2026-11-10 18:00', $e->previousEndTime->format('Y-m-d H:i'));
        $this->assertSame('2026-11-11 06:00', $e->endTime->format('Y-m-d H:i'));
    }

    /** @test */
    public function shortened_end_is_encurtado(): void
    {
        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00')]);
        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T15:00:00', '2026-11-10T07:30:00')]);

        $this->assertSame('encurtado', $this->events()->last()->kind);
    }

    /** @test */
    public function text_change_is_texto_alterado(): void
    {
        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00', 'Rajadas até 70 km/h.')]);
        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T07:30:00', 'Rajadas até 90 km/h.')]);

        $e = $this->events()->last();
        $this->assertSame('texto_alterado', $e->kind);
        $this->assertSame('Rajadas até 90 km/h.', $e->text);
        $this->assertSame('Rajadas até 70 km/h.', $e->previousText);
    }

    /** @test */
    public function partial_upgrade_is_agravado_encurtado_and_emitido(): void
    {
        $this->sync([$this->item('LSB', 'Precipitação', 'yellow', '2026-11-10T09:00:00', '2026-11-11T00:00:00', '2026-11-10T07:00:00')]);
        $this->sync([
            $this->item('LSB', 'Precipitação', 'yellow', '2026-11-10T09:00:00', '2026-11-10T12:00:00', '2026-11-10T07:30:00'),
            $this->item('LSB', 'Precipitação', 'orange', '2026-11-10T12:00:00', '2026-11-10T18:00:00', '2026-11-10T07:30:00'),
            $this->item('LSB', 'Precipitação', 'yellow', '2026-11-10T18:00:00', '2026-11-11T00:00:00', '2026-11-10T07:30:00'),
        ]);

        $kinds = $this->events()->slice(1)->pluck('kind')->sort()->values()->all();
        $this->assertSame(['agravado', 'emitido', 'encurtado'], $kinds);
        $agravado = $this->events()->firstWhere('kind', 'agravado');
        $this->assertSame('yellow', $agravado->fromLevel);
        $this->assertSame('orange', $agravado->toLevel);
    }

    /** @test */
    public function dropped_early_is_retirado(): void
    {
        $this->sync([
            $this->item('FAR', 'Vento', 'yellow', '2026-11-10T07:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00'),
            $this->item('FAR', 'Agitação Marítima', 'yellow', '2026-11-10T07:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00'),
        ]);
        $this->sync([$this->item('FAR', 'Agitação Marítima', 'yellow', '2026-11-10T07:00:00', '2026-11-10T18:00:00', '2026-11-10T07:30:00')]);

        $e = $this->events()->last();
        $this->assertSame('retirado', $e->kind);
        $this->assertSame('Vento', $e->type);
        $this->assertSame('yellow', $e->fromLevel);
        $this->assertNull($e->toLevel);
        $this->assertSame('2026-11-10 07:30', $e->occurredAt->format('Y-m-d H:i'));
    }

    /** @test */
    public function dropped_within_30_minutes_of_end_is_terminado(): void
    {
        $this->sync([
            $this->item('FAR', 'Vento', 'yellow', '2026-11-10T07:00:00', '2026-11-10T08:25:00', '2026-11-10T07:00:00'),
            $this->item('FAR', 'Agitação Marítima', 'yellow', '2026-11-10T07:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00'),
        ]);
        $this->sync([$this->item('FAR', 'Agitação Marítima', 'yellow', '2026-11-10T07:00:00', '2026-11-10T18:00:00', '2026-11-10T07:30:00')]);

        $e = $this->events()->last();
        $this->assertSame('terminado', $e->kind);
        $this->assertSame('2026-11-10 08:25', $e->occurredAt->format('Y-m-d H:i'));
        $this->assertNotNull(WeatherWarning::where('type', 'Vento')->first()->endedAt);
    }

    /** @test */
    public function expired_row_gets_one_terminado(): void
    {
        $this->sync([
            $this->item('FAR', 'Vento', 'yellow', '2026-11-10T07:00:00', '2026-11-10T09:00:00', '2026-11-10T07:00:00'),
            $this->item('FAR', 'Agitação Marítima', 'yellow', '2026-11-10T07:00:00', '2026-11-11T18:00:00', '2026-11-10T07:00:00'),
        ]);
        Carbon::setTestNow('2026-11-10T09:10:00');
        $feed = [
            $this->item('FAR', 'Vento', 'yellow', '2026-11-10T07:00:00', '2026-11-10T09:00:00', '2026-11-10T09:05:00'),
            $this->item('FAR', 'Agitação Marítima', 'yellow', '2026-11-10T07:00:00', '2026-11-11T18:00:00', '2026-11-10T09:05:00'),
        ];
        $this->sync($feed);
        Carbon::setTestNow('2026-11-10T09:25:00');
        $this->sync($feed);

        $ended = $this->events()->where('kind', 'terminado')->values();
        $this->assertCount(1, $ended);
        $this->assertSame('Vento', $ended[0]->type);
        $this->assertSame('2026-11-10 09:00', $ended[0]->occurredAt->format('Y-m-d H:i'));
        $this->assertCount(0, $this->events()->where('kind', 'retirado'));
    }

    /** @test */
    public function stale_or_empty_feed_records_nothing(): void
    {
        $this->sync([$this->item('STB', 'Vento', 'red', '2026-11-10T09:00:00', '2026-11-10T21:00:00', '2026-11-10T07:18:40')]);

        $this->sync(null);
        $this->sync([]);
        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T12:00:00', '2026-11-10T18:00:00', '2026-11-09T10:00:00')]);

        $this->assertSame(['emitido'], $this->events()->pluck('kind')->all());
    }

    /** @test */
    public function a_failing_event_write_does_not_break_the_sync(): void
    {
        $this->app->instance(WeatherWarningEventRecorder::class, new class () extends WeatherWarningEventRecorder {
            public function record(WeatherWarningDiff $diff, Carbon $now): void
            {
                throw new RuntimeException('mongo down');
            }
        });

        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00')]);

        $this->assertSame(1, WeatherWarning::whereNull('supersededAt')->count());
    }

    /** @test */
    public function rows_that_expired_before_the_log_started_get_no_terminado(): void
    {
        $old = new WeatherWarning();
        $old->district = 'LSB';
        $old->type = 'Vento';
        $old->level = 'yellow';
        $old->reportDate = '2026-11-05T07:00:00';
        $old->text = 'antigo';
        $old->startTime = '2026-11-05T09:00:00';
        $old->endTime = '2026-11-06T09:00:00';
        $old->save();

        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00')]);

        $this->assertSame(['emitido'], $this->events()->pluck('kind')->all());
    }

    /** @test */
    public function duplicate_rows_produce_one_event_when_the_level_changes(): void
    {
        $this->duplicateRows('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00');

        $this->sync([$this->item('STB', 'Vento', 'orange', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T07:30:00')]);

        $this->assertSame(['agravado'], $this->events()->pluck('kind')->all());
        $this->assertSame(1, WeatherWarning::whereNull('supersededAt')->count());
    }

    /** @test */
    public function duplicate_rows_produce_one_event_when_the_warning_is_dropped(): void
    {
        $this->duplicateRows('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00');

        $this->sync([$this->item('FAR', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T07:30:00')]);

        $this->assertSame(['emitido', 'retirado'], $this->events()->pluck('kind')->sort()->values()->all());
    }

    /** @test */
    public function a_split_merged_back_into_one_lower_window_is_desagravado(): void
    {
        $this->sync([
            $this->item('LSB', 'Precipitação', 'yellow', '2026-11-10T09:00:00', '2026-11-10T12:00:00', '2026-11-10T07:00:00'),
            $this->item('LSB', 'Precipitação', 'orange', '2026-11-10T12:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00'),
            $this->item('LSB', 'Precipitação', 'yellow', '2026-11-10T18:00:00', '2026-11-11T00:00:00', '2026-11-10T07:00:00'),
        ]);
        $this->sync([
            $this->item('LSB', 'Precipitação', 'yellow', '2026-11-10T09:00:00', '2026-11-11T00:00:00', '2026-11-10T07:30:00'),
        ]);

        $after = $this->events()->slice(3)->values();
        $this->assertSame(['desagravado'], $after->pluck('kind')->all());
        $this->assertSame('orange', $after[0]->fromLevel);
        $this->assertSame('yellow', $after[0]->toLevel);
        $this->assertSame(1, WeatherWarning::whereNull('supersededAt')->count());
    }

    /** @test */
    public function endings_are_recorded_even_when_the_feed_is_rejected(): void
    {
        $this->sync([$this->item('STB', 'Vento', 'yellow', '2026-11-10T07:00:00', '2026-11-10T09:00:00', '2026-11-10T07:00:00')]);
        Carbon::setTestNow('2026-11-10T09:10:00');

        $this->sync(null);

        $this->assertSame(['emitido', 'terminado'], $this->events()->pluck('kind')->all());
    }

    private function duplicateRows(string $district, string $type, string $level, string $start, string $end): void
    {
        foreach ([1, 2] as $_) {
            $w = new WeatherWarning();
            $w->district = $district;
            $w->type = $type;
            $w->level = $level;
            $w->reportDate = '2026-11-10T07:00:00';
            $w->text = 'Rajadas até 120 km/h.';
            $w->startTime = $start;
            $w->endTime = $end;
            $w->save();
        }
    }

    private function events()
    {
        return WeatherWarningEvent::orderBy('detectedAt')->orderBy('_id')->get();
    }

    private function sync(?array $items): void
    {
        (new HandleWeatherWarnings())->sync($items);
    }

    private function item(
        string $district,
        string $type,
        string $level,
        string $start,
        string $end,
        string $report = '2026-11-10T07:18:40',
        string $text = 'Rajadas até 120 km/h.'
    ): object {
        return (object) [
            'reportDate'        => $report,
            'text'              => $text,
            'awarenessTypeName' => $type,
            'idAreaAviso'       => $district,
            'startTime'         => $start,
            'awarenessLevelID'  => $level,
            'endTime'           => $end,
        ];
    }
}

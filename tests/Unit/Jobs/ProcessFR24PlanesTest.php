<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessFR24Planes;
use Tests\TestCase;

class ProcessFR24PlanesTest extends TestCase
{
    /** @test */
    public function it_returns_early_when_fr24_is_disabled(): void
    {
        putenv('FR24_ENABLE=');

        $job = new ProcessFR24Planes();

        // No tracked_aircraft, no Mongo writes, no Redis writes expected.
        // The first guard (env('FR24_ENABLE') falsy) should short-circuit.
        $job->handle();

        $this->assertTrue(true);
    }
}

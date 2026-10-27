<?php

namespace Tests\Unit\Tools;

use App\Jobs\ProcessRCM;
use App\Tools\TwitterTool;
use Tests\TestCase;

class TwitterToolTest extends TestCase
{
    private array $backup = [];

    protected function tearDown(): void
    {
        foreach ($this->backup as $name => $value) {
            $this->setEnv($name, $value);
        }

        parent::tearDown();
    }

    /** @test */
    public function a_failing_tweet_returns_null_instead_of_throwing(): void
    {
        $this->setEnv('TWITTER_ENABLE', 'true');
        // Incomplete credentials make the client constructor throw.
        $this->setEnv('TWITTER_CONSUMER_KEY_VOST', null);

        $this->assertNull(TwitterTool::tweet('test', false, false, false, true));
    }

    /** @test */
    public function rcm_jobs_are_unique_per_mode(): void
    {
        $ids = [
            (new ProcessRCM(false))->uniqueId(),
            (new ProcessRCM(true))->uniqueId(),
            (new ProcessRCM(true, true))->uniqueId(),
        ];

        $this->assertCount(3, array_unique($ids));
    }

    private function setEnv(string $name, ?string $value): void
    {
        if (!array_key_exists($name, $this->backup)) {
            $this->backup[$name] = $_SERVER[$name] ?? $_ENV[$name] ?? (getenv($name) === false ? null : getenv($name));
        }

        if ($value === null) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);

            return;
        }

        $_SERVER[$name] = $_ENV[$name] = $value;
        putenv("{$name}={$value}");
    }
}

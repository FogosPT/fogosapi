<?php

namespace Tests\Feature;

use App\Tools\ImageProcessingTool;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use DatabaseMigrations;

    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $name => $value) {
            $this->setEnv($name, $value);
        }

        parent::tearDown();
    }

    /** @test */
    public function write_endpoints_reject_requests_when_the_key_is_unset_or_empty(): void
    {
        foreach ([null, ''] as $configured) {
            $this->setEnv('API_WRITE_KEY', $configured);

            $this->postJson('v2/incidents/123/posit')->assertStatus(401);
            $this->postJson('v2/incidents/123/posit', [], ['key' => ''])->assertStatus(401);
            $this->postJson('v2/incidents/123/kml')->assertStatus(401);
            $this->postJson('v2/warnings/add')->assertStatus(401);
            $this->postJson('v2/warnings/add/agif')->assertStatus(401);
            $this->getJson('v2/rcm/update')->assertStatus(401);
        }
    }

    /** @test */
    public function write_endpoints_require_the_exact_key(): void
    {
        $this->setEnv('API_WRITE_KEY', 'correct-horse');

        $this->postJson('v2/incidents/123/posit', [], ['key' => 'wrong'])->assertStatus(401);
        // Past the key check the controller runs: the incident does not exist.
        $this->postJson('v2/incidents/123/posit', [], ['key' => 'correct-horse'])->assertStatus(404);
    }

    /** @test */
    public function the_moderator_photo_listing_requires_the_moderation_key(): void
    {
        foreach ([null, ''] as $configured) {
            $this->setEnv('PHOTO_MODERATION_KEY', $configured);

            $this->postJson('v2/incidents/123/photos/all')->assertStatus(401);
            $this->postJson('v2/incidents/123/photos/all', [], ['key' => ''])->assertStatus(401);
        }
    }

    /** @test */
    public function array_query_parameters_are_rejected(): void
    {
        $this->getJson('v2/incidents/active?subRegion[$ne]=x')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'invalid_parameters']);

        $this->getJson('fires/data?id[]=1')->assertStatus(422);
    }

    /** @test */
    public function responses_set_no_cookies(): void
    {
        $response = $this->get('v2/incidents/active');

        $response->assertOk();
        $this->assertEmpty($response->headers->getCookies());
    }

    /** @test */
    public function validation_errors_are_json_even_without_an_accept_header(): void
    {
        $this->get('v2/incidents/search?day=not-a-date')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['day']]);
    }

    /** @test */
    public function images_above_the_pixel_limit_are_refused_before_decoding(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pixel limit');

        ImageProcessingTool::process($this->pngHeader(20000, 20000));
    }

    /**
     * Signature + IHDR only: enough for getimagesizefromstring() to read the
     * declared dimensions without any pixel data.
     */
    private function pngHeader(int $width, int $height): string
    {
        $ihdr = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n"
            .pack('N', strlen($ihdr)).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
    }

    private function setEnv(string $name, ?string $value): void
    {
        if (!array_key_exists($name, $this->envBackup)) {
            $this->envBackup[$name] = $_SERVER[$name] ?? $_ENV[$name] ?? (getenv($name) === false ? null : getenv($name));
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

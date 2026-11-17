<?php

namespace Tests\Unit\Support;

use App\Support\S3DiskConfig;
use PHPUnit\Framework\TestCase;

class S3DiskConfigTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $values
     * @return callable(string, mixed=): mixed
     */
    private function env(array $values): callable
    {
        return static function (string $key, mixed $default = null) use ($values): mixed {
            return array_key_exists($key, $values) ? $values[$key] : $default;
        };
    }

    /** @test */
    public function it_uses_the_s3_values_together_when_s3_endpoint_is_set(): void
    {
        $disk = S3DiskConfig::fromEnv($this->env([
            'S3_ENDPOINT'        => 'http://fogos.s3:9000',
            'S3_ACCESS_KEY'      => 'new-key',
            'S3_SECRET_KEY'      => 'new-secret',
            'S3_REGION'          => 'eu-west-1',
            'S3_BUCKET'          => 'incident-photos',
            'S3_PUBLIC_BASE_URL' => 'https://cdn.fogos.pt/incident-photos',
            'MINIO_ENDPOINT'     => 'http://fogos.minio:9000',
            'MINIO_KEY'          => 'old-key',
            'MINIO_SECRET'       => 'old-secret',
            'MINIO_BUCKET'       => 'legacy-bucket',
            'MINIO_REGION'       => 'us-east-1',
            'MINIO_PUBLIC_BASE_URL' => 'https://cdn.fogos.pt/old',
        ]));

        $this->assertSame('new-key', $disk['key']);
        $this->assertSame('new-secret', $disk['secret']);
        $this->assertSame('http://fogos.s3:9000', $disk['endpoint']);
        $this->assertSame('eu-west-1', $disk['region']);
        $this->assertSame('incident-photos', $disk['bucket']);
        $this->assertSame('https://cdn.fogos.pt/incident-photos', $disk['url']);
        $this->assertTrue($disk['use_path_style_endpoint']);
        $this->assertSame('s3', $disk['driver']);
    }

    /** @test */
    public function it_keeps_minio_endpoint_and_credentials_together_while_s3_keys_are_only_staged(): void
    {
        $disk = S3DiskConfig::fromEnv($this->env([
            'S3_ACCESS_KEY'  => 'new-key',
            'S3_SECRET_KEY'  => 'new-secret',
            'S3_BUCKET'      => 'incident-photos',
            'MINIO_ENDPOINT' => 'http://fogos.minio:9000',
            'MINIO_KEY'      => 'old-key',
            'MINIO_SECRET'   => 'old-secret',
            'MINIO_BUCKET'   => 'incident-photos',
            'MINIO_PUBLIC_BASE_URL' => 'https://cdn.fogos.pt/incident-photos',
        ]));

        $this->assertSame('old-key', $disk['key']);
        $this->assertSame('old-secret', $disk['secret']);
        $this->assertSame('http://fogos.minio:9000', $disk['endpoint']);
        $this->assertSame('incident-photos', $disk['bucket']);
        $this->assertSame('https://cdn.fogos.pt/incident-photos', $disk['url']);
        $this->assertSame('us-east-1', $disk['region']);
    }

    /** @test */
    public function it_treats_an_empty_s3_endpoint_as_unset(): void
    {
        $disk = S3DiskConfig::fromEnv($this->env([
            'S3_ENDPOINT'    => '',
            'S3_ACCESS_KEY'  => 'new-key',
            'S3_SECRET_KEY'  => 'new-secret',
            'MINIO_ENDPOINT' => 'http://127.0.0.1:9000',
            'MINIO_KEY'      => 'old-key',
            'MINIO_SECRET'   => 'old-secret',
        ]));

        $this->assertSame('old-key', $disk['key']);
        $this->assertSame('old-secret', $disk['secret']);
        $this->assertSame('http://127.0.0.1:9000', $disk['endpoint']);
    }

    /** @test */
    public function it_falls_back_to_minio_root_credentials_when_app_keys_are_absent(): void
    {
        $disk = S3DiskConfig::fromEnv($this->env([
            'MINIO_ENDPOINT'      => 'http://fogos.minio:9000',
            'MINIO_ROOT_USER'     => 'root-user',
            'MINIO_ROOT_PASSWORD' => 'root-pass',
            'MINIO_BUCKET'        => 'incident-photos',
        ]));

        $this->assertSame('root-user', $disk['key']);
        $this->assertSame('root-pass', $disk['secret']);
    }
}

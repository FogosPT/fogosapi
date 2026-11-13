<?php

namespace Tests\Unit\Support\Aemet;

use App\Support\Aemet\AemetZones;
use App\Support\Aemet\CapMessage;
use App\Support\Aemet\CapTar;
use Tests\TestCase;

class CapMessageTest extends TestCase
{
    private function fixtureTar(): string
    {
        return file_get_contents(base_path('tests/fixtures/aemet/avisos.tar'));
    }

    /** @return array<string, CapMessage> keyed by identifier */
    private function messages(): array
    {
        $out = [];
        foreach (CapTar::messages($this->fixtureTar()) as $message) {
            $out[$message->identifier] = $message;
        }

        return $out;
    }

    private function message(string $needle): CapMessage
    {
        foreach ($this->messages() as $identifier => $message) {
            if (str_contains($identifier, $needle)) {
                return $message;
            }
        }
        $this->fail("No message matching {$needle}");
    }

    /** @test */
    public function parses_a_real_message(): void
    {
        $m = $this->message('713201PRP1');

        $this->assertSame('2.49.0.0.724.0.ES.20261112170709.713201PRP113071794503229', $m->identifier);
        $this->assertSame('Alert', $m->msgType);
        $this->assertSame([], $m->references);
        $this->assertSame('2026-11-12T17:07:09Z', $m->sent->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame('UTC', $m->sent->getTimezone()->getName());
        $this->assertSame('amarillo', $m->level);
        $this->assertSame('PR', $m->phenomenon);
        $this->assertSame('Lluvias', $m->phenomenonName);
        $this->assertSame('2026-11-12T17:00:00Z', $m->onset->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame('UTC', $m->onset->getTimezone()->getName());
        $this->assertSame('2026-11-13T06:59:59Z', $m->expires->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame('Moderate rain warning. Noroeste de Ourense', $m->en['headline']);
        $this->assertSame('Aviso de lluvias de nivel amarillo. Noroeste de Ourense', $m->es['headline']);
        $this->assertSame('Precipitación acumulada en una hora: 15 mm', $m->es['parameter']);
        $this->assertSame('One-hour accumulated precipitation: 15 mm', $m->en['parameter']);
        $this->assertSame('40%-70%', $m->es['probability']);
        $this->assertStringStartsWith('Be aware', $m->en['instruction']);
        $this->assertStringStartsWith('Precipitación acumulada', $m->es['description']);
        $this->assertCount(1, $m->areas);
        $this->assertSame('713201', $m->areas[0]['code']);
        $this->assertSame('Noroeste de Ourense', $m->areas[0]['name']);
        $this->assertStringContainsString('<identifier>', $m->raw);
    }

    /** @test */
    public function polygons_are_lon_lat(): void
    {
        $m = $this->message('774601VIRM');
        $polygons = $m->areas[0]['polygons'];

        $this->assertCount(2, $polygons);
        [$lon, $lat] = $polygons[0][0];
        $this->assertIsFloat($lon);
        $this->assertIsFloat($lat);
        $this->assertLessThan(0, $lon);
        $this->assertGreaterThan(38, $lat);
        $this->assertLessThan(40.5, $lat);
        $this->assertSame([-1.5, 39.42], $polygons[0][0]);
    }

    /** @test */
    public function areas_come_from_the_spanish_info_only(): void
    {
        $m = $this->message('71VV71PRP1');

        $this->assertSame('verde', $m->level);
        $this->assertCount(16, $m->areas);
        $this->assertCount(16, array_unique(array_column($m->areas, 'code')));
    }

    /** @test */
    public function references_are_identifiers(): void
    {
        $messages = $this->messages();

        $this->assertSame('Update', $messages['TEST.NEW']->msgType);
        $this->assertSame(['TEST.OLD'], $messages['TEST.NEW']->references);
        $this->assertSame([], $messages['TEST.OLD']->references);
    }

    /** @test */
    public function tar_yields_all_files(): void
    {
        $this->assertCount(9, CapTar::messages($this->fixtureTar()));
    }

    /** @test */
    public function tar_with_garbage_throws(): void
    {
        $dir = sys_get_temp_dir().'/captest-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $path = $dir.'/garbage.tar';

        try {
            $tar = new \PharData($path);
            $tar->addFromString('garbage.xml', 'this is not xml');
            unset($tar);
            $bytes = file_get_contents($path);
        } finally {
            @unlink($path);
            @rmdir($dir);
        }

        $this->expectException(\RuntimeException::class);
        CapTar::messages($bytes);
    }

    /** @test */
    public function tar_leaves_no_temp_files(): void
    {
        $before = glob(sys_get_temp_dir().'/aemet-cap-*') ?: [];
        CapTar::messages($this->fixtureTar());

        try {
            CapTar::messages('not a tar at all');
        } catch (\RuntimeException) {
        }
        $after = glob(sys_get_temp_dir().'/aemet-cap-*') ?: [];

        $this->assertSame($before, $after);
    }

    /** @test */
    public function zones_peninsula_and_coastal(): void
    {
        $this->assertFalse(AemetZones::isPeninsula('645405'));
        $this->assertFalse(AemetZones::isPeninsula('645405C'));
        $this->assertFalse(AemetZones::isPeninsula('651234'));
        $this->assertFalse(AemetZones::isPeninsula('785101'));
        $this->assertFalse(AemetZones::isPeninsula('795201'));
        $this->assertTrue(AemetZones::isPeninsula('713603C'));
        $this->assertTrue(AemetZones::isPeninsula('713603'));
        $this->assertTrue(AemetZones::isCoastal('713603C'));
        $this->assertFalse(AemetZones::isCoastal('713603'));
    }

    /** @test */
    public function geometry_is_a_closed_multipolygon(): void
    {
        $geometry = AemetZones::geometry([
            [[-1.0, 39.0], [-1.1, 39.1], [-1.2, 39.0]],
            [[-2.0, 40.0], [-2.1, 40.1], [-2.2, 40.0], [-2.0, 40.0]],
        ]);

        $this->assertSame('MultiPolygon', $geometry['type']);
        $this->assertSame([
            [[[-1.0, 39.0], [-1.1, 39.1], [-1.2, 39.0], [-1.0, 39.0]]],
            [[[-2.0, 40.0], [-2.1, 40.1], [-2.2, 40.0], [-2.0, 40.0]]],
        ], $geometry['coordinates']);
    }
}

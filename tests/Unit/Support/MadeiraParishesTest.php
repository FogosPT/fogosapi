<?php

namespace Tests\Unit\Support;

use App\Console\Commands\ImportMadeiraParishes;
use App\Support\MadeiraParishes;
use Tests\TestCase;

class MadeiraParishesTest extends TestCase
{
    /** @test */
    public function the_table_has_the_54_parishes_of_the_region_on_the_islands(): void
    {
        $all = MadeiraParishes::all();

        $this->assertCount(54, $all);
        foreach ($all as $code => [$name, $municipality, $lat, $lng]) {
            $this->assertMatchesRegularExpression('/^3[12]\d{4}$/', (string) $code);
            // Madeira + Porto Santo, not the Selvagens (~30°N) or the Desertas (~32.5°N).
            $this->assertTrue($lat >= 32.6 && $lat <= 33.2 && $lng >= -17.3 && $lng <= -16.2, "$code $name");
        }
    }

    /** @test */
    public function parishes_are_found_by_code(): void
    {
        $se = MadeiraParishes::find('310310');

        $this->assertSame('Funchal (Sé)', $se['name']);
        $this->assertSame('Funchal', $se['municipality']);
        $this->assertEqualsWithDelta(32.646, $se['lat'], 0.01);
        $this->assertEqualsWithDelta(-16.914, $se['lng'], 0.01);
    }

    /** @test */
    public function an_unknown_code_falls_back_to_the_name_inside_the_municipality(): void
    {
        // Seen in the SRPC-RAM data: 310801 "Santa Cruz" (CAOP: 310805), also without the name.
        $this->assertSame('310805', MadeiraParishes::find('310801', 'Santa Cruz')['code']);
        $this->assertSame('310805', MadeiraParishes::find('310801')['code']);
        // Accents/case and the source's "Funchal (…)" spelling.
        $this->assertSame('310404', MadeiraParishes::find('310499', 'porto da Cruz')['code']);
        $this->assertSame('310310', MadeiraParishes::find(null, 'Funchal (Sé)')['code']);
        // Same name in two municipalities: the code's municipality decides.
        $this->assertSame('310405', MadeiraParishes::find('310499', 'Santo António da Serra')['code']);
        $this->assertSame('310806', MadeiraParishes::find('310899', 'Santo António da Serra')['code']);

        $this->assertNull(MadeiraParishes::find('310899', null));
        $this->assertNull(MadeiraParishes::find(null, null));
        $this->assertNull(MadeiraParishes::find('310899', 'Lisboa'));
    }

    /** @test */
    public function the_centroid_ignores_islets_far_south(): void
    {
        // A parish made of a big polygon at ~30°N (Selvagens) and a small one in Funchal.
        $square = fn ($lng, $lat, $size) => [[[$lng, $lat], [$lng + $size, $lat], [$lng + $size, $lat + $size], [$lng, $lat + $size], [$lng, $lat]]];
        $geometry = ['type' => 'MultiPolygon', 'coordinates' => [$square(-15.9, 30.1, 0.05), $square(-16.92, 32.64, 0.01)]];

        [$lng, $lat] = ImportMadeiraParishes::centroid($geometry);

        $this->assertEqualsWithDelta(32.645, $lat, 0.001);
        $this->assertEqualsWithDelta(-16.915, $lng, 0.001);
    }
}

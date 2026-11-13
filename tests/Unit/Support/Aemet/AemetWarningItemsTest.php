<?php

namespace Tests\Unit\Support\Aemet;

use App\Support\Aemet\AemetWarningItems;
use App\Support\Aemet\CapMessage;
use App\Support\Aemet\CapTar;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Fixture tests/fixtures/aemet/avisos.tar (times as written in the files, +01:00):
 *
 * | file | identifier | level | zone(s) | onset | expires |
 * |---|---|---|---|---|---|
 * | Z_CAP_C_LEMM_20261112170709_AFAZ713201PRP11307.xml | 2.49.0.0.724.0.ES.20261112170709.713201PRP113071794503229 (Alert) | amarillo | 713201 | 2026-11-12T18:00+01:00 | 2026-11-13T07:59:59+01:00 |
 * | Z_CAP_C_LEMM_20261112170709_AFAZ713201PRP21307.xml | 2.49.0.0.724.0.ES.20261112170709.713201PRP213071794503229 (Update) | amarillo | 713201 | 2026-11-12T19:00+01:00 | 2026-11-13T07:59:59+01:00 |
 * | Z_CAP_C_LEMM_20261112092714_AFAZ713603PRP11223.xml | 2.49.0.0.724.0.ES.20261112092714.713603PRP112231794475634 (Update) | amarillo | 713603 (border) | 2026-11-12T10:00+01:00 | 2026-11-12T23:59:59+01:00 |
 * | Z_CAP_C_LEMM_20261111102457_AFAZ645405COCO1223.xml | 2.49.0.0.724.0.ES.20261111102457.645405COCO12231794392697 (Update) | naranja (Severe) | 645405C (Baleares) | 2026-11-12T03:00+01:00 | 2026-11-12T23:59:59+01:00 |
 * | Z_CAP_C_LEMM_20261111102353_AFAZ774601VIRM1214.xml | 2.49.0.0.724.0.ES.20261111102353.774601VIRM12141794392633 (Alert) | amarillo | 774601, 2 polygons per info | 2026-11-12T07:00+01:00 | 2026-11-12T14:59:59+01:00 |
 * | Z_CAP_C_LEMM_20261111102556_AFAZ622203VIRM1211.xml | 2.49.0.0.724.0.ES.20261111102556.622203VIRM12111794392756 (Update) | amarillo | 622203 | 2026-11-12T06:00+01:00 | 2026-11-12T11:59:59+01:00 (expired) |
 * | Z_CAP_C_LEMM_20261111225001_AFAZ71VV71PRP11423.xml | 2.49.0.0.724.0.ES.20261111225001.71VV71PRP114231794437401 (Alert) | verde (Minor) | 16 zones per info, 32 `<area>` in total, including 713201 and 713603 | 2026-11-14T00:00+01:00 | 2026-11-14T23:59:59+01:00 |
 * | TEST.OLD.xml | TEST.OLD (Alert, no `<references>`, sent 2026-11-12T08:00:00Z) | amarillo | 713603 | 2026-11-12T10:00+01:00 | 2026-11-12T23:59:59+01:00 |
 * | TEST.NEW.xml | TEST.NEW (Update, references `http://www.aemet.es,TEST.OLD,2026-11-12T08:00:00-00:00`, sent 09:00Z) | amarillo | 713603 | 2026-11-12T10:00+01:00 | 2026-11-12T23:59:59+01:00 |
 */
class AemetWarningItemsTest extends TestCase
{
    private const P1 = '2.49.0.0.724.0.ES.20261112170709.713201PRP113071794503229';
    private const P2 = '2.49.0.0.724.0.ES.20261112170709.713201PRP213071794503229';
    private const VERDE = '2.49.0.0.724.0.ES.20261111225001.71VV71PRP114231794437401';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12T16:00:00Z');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return CapMessage[] */
    private function messages(): array
    {
        return CapTar::messages(file_get_contents(base_path('tests/fixtures/aemet/avisos.tar')));
    }

    private function message(string $identifier): CapMessage
    {
        foreach ($this->messages() as $message) {
            if ($message->identifier === $identifier) {
                return $message;
            }
        }
        $this->fail("No message {$identifier}");
    }

    /** @return object[] */
    private function items(?array $messages = null): array
    {
        return AemetWarningItems::fromMessages($messages ?? $this->messages(), Carbon::now());
    }

    /** A synthetic message: the P1 XML with the given replacements. */
    private function synthetic(array $replacements): CapMessage
    {
        return CapMessage::fromXml(strtr($this->message(self::P1)->raw, $replacements));
    }

    /** @test */
    public function verde_and_expired_are_dropped(): void
    {
        $items = $this->items();

        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertNotSame('622203', $item->idAreaAviso);
            $this->assertNotContains(self::VERDE, $item->identifiers);
            $this->assertContains($item->awarenessLevelID, ['yellow', 'orange', 'red']);
        }
    }

    /** @test */
    public function references_supersede(): void
    {
        $identifiers = array_merge(...array_map(fn ($i) => $i->identifiers, $this->items()));

        $this->assertNotContains('TEST.OLD', $identifiers);
        $this->assertContains('TEST.NEW', $identifiers);
    }

    /** @test */
    public function cancel_and_what_it_cancels_are_dropped(): void
    {
        $cancel = $this->synthetic([
            self::P1 => 'TEST.CANCEL',
            '<msgType>Alert</msgType>' => '<msgType>Cancel</msgType><references>http://www.aemet.es,'.self::P2.',2026-11-12T17:07:09-00:00</references>',
        ]);
        $items = $this->items([...$this->messages(), $cancel]);

        $identifiers = array_merge(...array_map(fn ($i) => $i->identifiers, $items));
        $this->assertNotContains('TEST.CANCEL', $identifiers);
        $this->assertNotContains(self::P2, $identifiers);
        $this->assertContains(self::P1, $identifiers);
    }

    /** @test */
    public function same_level_overlaps_are_merged(): void
    {
        $matching = array_values(array_filter($this->items(), fn ($i) => '713201' === $i->idAreaAviso
            && 'Precipitação' === $i->awarenessTypeName && 'yellow' === $i->awarenessLevelID));

        $this->assertCount(1, $matching);
        $item = $matching[0];
        $p1 = $this->message(self::P1);
        $p2 = $this->message(self::P2);

        $this->assertSame([self::P1, self::P2], $item->identifiers);
        $this->assertTrue(Carbon::parse($item->startTime)->eq($p1->onset));
        $this->assertTrue(Carbon::parse($item->endTime)->eq($p1->expires));
        $this->assertSame($p1->es['parameter'].'; '.$p2->es['parameter'], $item->parameter);
        $this->assertSame($p1->es['description'].' '.$p2->es['description'], $item->text);
        $this->assertTrue(Carbon::parse($item->reportDate)->eq($p1->sent));
        $this->assertSame('PR', $item->phenomenon);
        $this->assertSame($p1->en, $item->en);
    }

    /** @test */
    public function back_to_back_windows_are_not_merged(): void
    {
        $later = $this->synthetic([
            self::P1 => 'TEST.LATER',
            '2026-11-12T18:00:00+01:00' => '2026-11-13T07:59:59+01:00',
            '2026-11-13T07:59:59+01:00' => '2026-11-13T12:00:00+01:00',
        ]);
        $items = array_filter($this->items([$this->message(self::P1), $later]), fn ($i) => '713201' === $i->idAreaAviso);

        $this->assertCount(2, $items);
    }

    /** @test */
    public function merging_is_transitive(): void
    {
        // A 10–12, B 11–14, C 13–16: A and C do not overlap, but B joins them.
        $make = fn (string $id, string $onset, string $expires) => $this->synthetic([
            self::P1 => $id,
            '2026-11-12T18:00:00+01:00' => $onset,
            '2026-11-13T07:59:59+01:00' => $expires,
        ]);
        $items = $this->items([
            $make('TEST.C', '2026-11-13T13:00:00+00:00', '2026-11-13T16:00:00+00:00'),
            $make('TEST.A', '2026-11-13T10:00:00+00:00', '2026-11-13T12:00:00+00:00'),
            $make('TEST.B', '2026-11-13T11:00:00+00:00', '2026-11-13T14:00:00+00:00'),
        ]);

        $this->assertCount(1, $items);
        $this->assertSame(['TEST.A', 'TEST.B', 'TEST.C'], $items[0]->identifiers);
        $this->assertTrue(Carbon::parse($items[0]->startTime)->eq(Carbon::parse('2026-11-13T10:00:00Z')));
        $this->assertTrue(Carbon::parse($items[0]->endTime)->eq(Carbon::parse('2026-11-13T16:00:00Z')));
    }

    /** @test */
    public function one_item_per_area(): void
    {
        // The verde file has 16 zones; as amarillo it gives one item per zone.
        $verde = $this->message(self::VERDE);
        $amarillo = CapMessage::fromXml(str_replace('<value>verde</value>', '<value>amarillo</value>', $verde->raw));
        $items = $this->items([$amarillo]);

        $this->assertCount(16, $items);
        $this->assertCount(16, array_unique(array_map(fn ($i) => $i->idAreaAviso, $items)));
        foreach ($items as $item) {
            $this->assertNotEmpty($item->areaName);
        }
    }

    /** @test */
    public function types_are_ipma_names(): void
    {
        $coastal = array_values(array_filter($this->items(), fn ($i) => str_starts_with($i->idAreaAviso, '645405')));
        $this->assertCount(1, $coastal);
        $this->assertSame('Agitação Marítima', $coastal[0]->awarenessTypeName);
        $this->assertSame('orange', $coastal[0]->awarenessLevelID);

        $dust = $this->synthetic(['PR;Lluvias' => 'VS;Polvo en suspensión']);
        $items = $this->items([$dust]);
        $this->assertCount(1, $items);
        $this->assertSame('Polvo en suspensión', $items[0]->awarenessTypeName);
        $this->assertSame('VS', $items[0]->phenomenon);
    }

    /** @test */
    public function summer_offset_is_converted(): void
    {
        $summer = $this->synthetic([
            '2026-11-12T18:00:00+01:00' => '2026-07-01T10:00:00+02:00',
            '2026-11-13T07:59:59+01:00' => '2026-12-01T00:00:00+01:00',
        ]);
        $items = $this->items([$summer]);

        $this->assertCount(1, $items);
        $this->assertSame('2026-07-01T08:00:00Z', Carbon::parse($items[0]->startTime)->utc()->format('Y-m-d\TH:i:s\Z'));
        $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/', $items[0]->startTime);
    }

    /** @test */
    public function output_is_deterministic(): void
    {
        $messages = $this->messages();
        $expected = $this->items($messages);

        mt_srand(42);
        for ($i = 0; $i < 5; ++$i) {
            shuffle($messages);
            $this->assertEquals($expected, $this->items($messages));
        }
        $this->assertSame(json_encode($expected), json_encode($this->items(array_reverse($messages))));
    }
}

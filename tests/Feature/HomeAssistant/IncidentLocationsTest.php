<?php

namespace Tests\Feature\HomeAssistant;

use App\Models\Location;
use Database\Factories\IncidentFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IncidentLocationsTest extends TestCase
{
    use DatabaseMigrations;

    private const UNIAO = 'União das freguesias de Belazaima do Chão, Castanheira do Vouga e Agadão';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function incident(string $district, string $concelho, ?string $freguesia, array $extra = [])
    {
        return IncidentFactory::new()->active()->fire()->create(array_merge([
            'district' => $district,
            'concelho' => $concelho,
            'freguesia' => $freguesia,
        ], $extra));
    }

    /** @test */
    public function locations_lists_distinct_names_as_a_sorted_tree(): void
    {
        $this->incident('Porto', 'Vila Nova De Gaia', 'Canidelo', ['dico' => '1317']);
        $this->incident('Porto', 'Vila Nova De Gaia', 'Canidelo', ['dico' => '1317']);
        $this->incident('Porto', 'vila nova de gaia', 'Avintes', ['dico' => '1317']);
        $this->incident('Porto', 'Amarante', null, ['dico' => '1301']);
        $this->incident('Aveiro', 'Águeda', self::UNIAO, ['dico' => '0101']);
        $this->incident('', 'Sem Distrito', 'X');

        $data = $this->getJson('v2/incidents/locations')
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->json('data');

        $this->assertSame([
            [
                'district' => 'Aveiro',
                'concelhos' => [['concelho' => 'Águeda', 'dico' => '0101', 'freguesias' => [self::UNIAO]]],
            ],
            [
                'district' => 'Porto',
                'concelhos' => [
                    ['concelho' => 'Amarante', 'dico' => '1301', 'freguesias' => []],
                    // Grouped case-insensitively; the most frequent spelling is shown.
                    ['concelho' => 'Vila Nova De Gaia', 'dico' => '1317', 'freguesias' => ['Avintes', 'Canidelo']],
                ],
            ],
        ], $data);
    }

    /** @test */
    public function active_filters_by_several_locations_at_any_level(): void
    {
        $porto = $this->incident('Porto', 'Amarante', 'Telões');
        $guimaraes = $this->incident('Braga', 'Guimarães', 'Azurém');
        $this->incident('Braga', 'Barcelos', 'Areias');
        $uniao = $this->incident('Aveiro', 'Águeda', self::UNIAO);
        $this->incident('Aveiro', 'Ovar', 'Esmoriz');

        $ids = fn (string $query) => collect($this->getJson('v2/incidents/active?'.$query)->assertOk()->json('data'))
            ->pluck('id')->sort()->values()->all();
        $expected = fn (...$incidents) => collect($incidents)->pluck('id')->sort()->values()->all();

        $this->assertSame($expected($porto, $guimaraes), $ids('district=Porto&concelho=Guimar%C3%A3es'));
        $this->assertSame($expected($porto, $guimaraes), $ids('concelho='.urlencode('Amarante|guimarães')));
        $this->assertSame($expected($porto, $guimaraes), $ids('concelho='.urlencode('Amarante,Guimarães')));
        // A parish whose name has commas is one value.
        $this->assertSame($expected($uniao), $ids('freguesia='.urlencode(self::UNIAO)));
        $this->assertSame($expected($uniao, $porto), $ids('freguesia='.urlencode(self::UNIAO.'|Telões')));
    }

    /** @test */
    public function search_and_new_fires_accept_the_same_filters(): void
    {
        $porto = $this->incident('Porto', 'Amarante', 'Telões', ['dateTime' => now()]);
        $braga = $this->incident('Braga', 'Guimarães', 'Azurém', ['dateTime' => now()]);
        $this->incident('Aveiro', 'Ovar', 'Esmoriz', ['dateTime' => now()]);

        $search = $this->getJson('v2/incidents/search?day='.now()->format('Y-m-d').'&district='.urlencode('Porto|Braga'))
            ->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$porto->id, $braga->id], array_column($search, 'id'));

        $legacy = $this->getJson('new/fires?distrito=Porto&concelho=Guimar%C3%A3es')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$porto->id, $braga->id], array_column($legacy, 'id'));

        // Legacy contract: unknown names return no fires, not a 422.
        $this->getJson('new/fires?distrito=Narnia')->assertOk()->assertJsonCount(0, 'data');
    }

    /** @test */
    public function unknown_locations_are_rejected(): void
    {
        $this->incident('Porto', 'Amarante', 'Telões');
        Location::create(['level' => 1, 'code' => 13, 'name' => 'Porto']);

        $this->getJson('v2/incidents/active?district=Porto|Narnia')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'invalid_district',
                'invalid' => ['district' => ['Narnia']],
            ]);

        $this->getJson('v2/incidents/active?freguesia=Nenhures')
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_freguesia']);
    }

    /** @test */
    public function incidents_expose_their_kind(): void
    {
        $kinds = [
            'fire' => IncidentFactory::new()->active()->fire()->create(),
            'urbanFire' => IncidentFactory::new()->active()->urbanFire()->create(['naturezaCode' => '2101']),
            'transportFire' => IncidentFactory::new()->active()->transportFire()->create(['naturezaCode' => '2301']),
            'otherFire' => IncidentFactory::new()->active()->otherFire()->create(['naturezaCode' => '3201']),
            'fma' => IncidentFactory::new()->active()->other()->create(['naturezaCode' => '3315', 'isFMA' => true]),
            'other' => IncidentFactory::new()->active()->other()->create(['naturezaCode' => '4512']),
        ];

        $byId = collect($this->getJson('v2/incidents/active?all=1')->assertOk()->json('data'))->pluck('kind', 'id');

        foreach ($kinds as $kind => $incident) {
            $this->assertSame($kind, $byId[$incident->id], $kind);
        }
    }
}

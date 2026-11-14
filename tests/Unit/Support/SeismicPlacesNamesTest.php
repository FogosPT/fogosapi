<?php

namespace Tests\Unit\Support;

use App\Support\SeismicPlaces;
use Tests\TestCase;

class SeismicPlacesNamesTest extends TestCase
{
    /** @test */
    public function municipalities_are_resolved_to_the_gazetteer_names(): void
    {
        $this->assertSame(['Praia da Vitória'], SeismicPlaces::municipalitiesFor(['Vila da Praia da Vitória']));
        $this->assertSame(['Castanheira de Pera'], SeismicPlaces::municipalitiesFor(['Castanheira de Pêra']));
        $this->assertSame(['Vila do Conde'], SeismicPlaces::municipalitiesFor(['VILA DO CONDE']));
        $this->assertSame([], SeismicPlaces::municipalitiesFor(['Narnia']));
    }

    /** @test */
    public function parishes_are_resolved_to_the_gazetteer_names(): void
    {
        $this->assertEqualsCanonicalizing(
            [['Freguesia de Águeda', 'Águeda'], ['Freguesia de Borralha', 'Águeda']],
            SeismicPlaces::parishesFor([['União das freguesias de Águeda e Borralha', 'Águeda']])
        );
        $union = SeismicPlaces::parishesFor([['União das freguesias de Belazaima do Chão, Castanheira do Vouga e Agadão', 'Águeda']]);
        $this->assertContains(['Freguesia de Belazaima do Chão', 'Águeda'], $union);
        $this->assertContains(['Freguesia de Agadão', 'Águeda'], $union);
        $this->assertSame([['Santa Cruz', 'Praia da Vitória']], SeismicPlaces::parishesFor([['Praia da Vitória (Santa Cruz)', 'Vila da Praia da Vitória']]));
    }
}

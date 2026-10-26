<?php

namespace Tests\Unit\Tools;

use App\Tools\ICNFTool;
use Carbon\Carbon;
use Tests\TestCase;

class ICNFToolTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function it_parses_occurrences_from_faztable(): void
    {
        $rows = ICNFTool::parseTable($this->fixture());

        $this->assertCount(3, $rows);

        $this->assertSame('20261520072', $rows[0]['id']);
        $this->assertSame('Em Curso', $rows[0]['estado']);
        $this->assertSame('2026-10-26 13:53:00', $rows[0]['startedAt']->format('Y-m-d H:i:s'));

        $this->assertSame('20261050574', $rows[1]['id']);
        $this->assertSame('Dominado', $rows[1]['estado']);

        $this->assertSame('20260000026', $rows[2]['id']);
        $this->assertSame('Extinto', $rows[2]['estado']);
    }

    /** @test */
    public function it_skips_non_numeric_ids(): void
    {
        $ids = array_column(ICNFTool::parseTable($this->fixture()), 'id');

        $this->assertNotContains('RO3211180', $ids);
    }

    /** @test */
    public function it_maps_icnf_states_to_incident_status(): void
    {
        $this->assertSame(['Conclusão', 8, '8e7e7d'], ICNFTool::mapStatus('Extinto'));
        $this->assertSame(['Em Resolução', 7, '65C4ED'], ICNFTool::mapStatus('Dominado'));
        $this->assertSame(['Em Curso', 5, 'B81E1F'], ICNFTool::mapStatus('Em Curso'));
    }

    /** @test */
    public function it_decides_whether_an_occurrence_is_active(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 15:00:00', 'Europe/Lisbon'));

        $recent = Carbon::parse('2026-10-26 10:00:00', 'Europe/Lisbon');
        $old = Carbon::parse('2026-07-26 13:29:00', 'Europe/Lisbon');

        $this->assertTrue(ICNFTool::isActive('Em Curso', $old));
        $this->assertTrue(ICNFTool::isActive('Dominado', $recent));
        $this->assertFalse(ICNFTool::isActive('Dominado', $old));
        $this->assertFalse(ICNFTool::isActive('Dominado', null));
        $this->assertFalse(ICNFTool::isActive('Extinto', $recent));
    }

    private function fixture(): string
    {
        return <<<'HTML'
<script type="text/javascript">
    google.charts.load('current', {'packages':['table']});
    function drawTable() {
    var data =  new google.visualization.arrayToDataTable([
		[{label: 'Codigo', type: 'string'},{label: 'Tipo', type: 'string'},{label: 'DHInicio', type: 'string'},{label: 'Dur', type: 'string'},{label: 'AreaPov', type: 'number'},{label: 'AreaMato', type: 'number'},{label: 'AreaAgric', type: 'number'},{label: 'AreaTotal', type: 'number'},{label: 'Concelho', type: 'string'},{label: 'Local', type: 'string'},{label: 'lat', type: 'number'},{label: 'lon', type: 'number'},{label: 'estado', type: 'string'},{label: 'linkG', type: 'string'},{label: 'fich', type: 'string'}],
['<a target="_blank" href="../sgif2010/IncPrint.asp?Codigo=20261520072">20261520072</a>','Agricola','26/10/2026 13:53:00','59m','0','0','0','0','Penafiel','Rua do Tapado Novo (Bensal)','41.1368479998906','-8.27197500016954','Em Curso','<a target="_blank" href="https://fogos.icnf.pt/localizador/apoio_Decisao.asp?izoom=15&latdec=41.1368&londec=-8.27198">g</a>',''],
['<a target="_blank" href="../sgif2010/IncPrint.asp?Codigo=20261050574">20261050574</a>','Florestal','26/07/2026 13:29:00','2209h23m','0','0.33','0','0.33','Paredes','Monte Azevido','41.2172222222222','-8.40555555555556','Dominado','<a target="_blank" href="https://fogos.icnf.pt/localizador/localizador2018.asp?latdec=41.2172&londec=-8.40556">g</a>',''],
['<a target="_blank" href="../sgif2010/IncPrint.asp?Codigo=20260000026">20260000026</a>','Agricola','01/01/2026 00:08:00','26m','0','0','0.0006','0.0006','Esposende','Av.Praia (MAR)','41.5735449997584','-8.79127000013987','Extinto','<a target="_blank" href="https://fogos.icnf.pt/localizador/apoio_Decisao.asp?izoom=15&latdec=41.5735&londec=-8.79127">g</a>',''],
['<a target="_blank" href="../sgif2010/IncPrint.asp?Codigo=RO3211180">RO3211180</a>','Agricola','01/07/2026 19:02:00','20m','0','0','0.0047','0.0047','Moita','Cortagueira','38.6149919997321','-8.9995119995541','Extinto','<a target="_blank" href="https://fogos.icnf.pt/localizador/localizador2018.asp?latdec=38.615&londec=-8.99951">g</a>','']
        ]);
</script>
HTML;
    }
}

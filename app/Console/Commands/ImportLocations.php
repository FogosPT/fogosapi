<?php

namespace App\Console\Commands;

use App\Models\Location;
use Illuminate\Console\Command;

class ImportLocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fogospt:import-locations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $locations = json_decode('
[
  {
    "level": 1,
    "code": 1,
    "name": "Aveiro"
  },
  {
    "level": 2,
    "code": 101,
    "name": "Águeda"
  },
  {
    "level": 3,
    "code": 10103,
    "name": "Aguada de Cima"
  },
  {
    "level": 3,
    "code": 10109,
    "name": "Fermentelos"
  },
  {
    "level": 3,
    "code": 10112,
    "name": "Macinhata do Vouga"
  },
  {
    "level": 3,
    "code": 10119,
    "name": "Valongo do Vouga"
  },
  {
    "level": 3,
    "code": 10121,
    "name": "União das freguesias de Águeda e Borralha"
  },
  {
    "level": 3,
    "code": 10122,
    "name": "União das freguesias de Barrô e Aguada de Baixo"
  },
  {
    "level": 3,
    "code": 10123,
    "name": "União das freguesias de Belazaima do Chão, Castanheira do Vouga e Agadão"
  },
  {
    "level": 3,
    "code": 10124,
    "name": "União das freguesias de Recardães e Espinhel"
  },
  {
    "level": 3,
    "code": 10125,
    "name": "União das freguesias de Travassô e Óis da Ribeira"
  },
  {
    "level": 3,
    "code": 10126,
    "name": "União das freguesias de Trofa, Segadães e Lamas do Vouga"
  },
  {
    "level": 3,
    "code": 10127,
    "name": "União das freguesias do Préstimo e Macieira de Alcoba"
  },
  {
    "level": 2,
    "code": 102,
    "name": "Albergaria-a-Velha"
  },
  {
    "level": 3,
    "code": 10202,
    "name": "Alquerubim"
  },
  {
    "level": 3,
    "code": 10203,
    "name": "Angeja"
  },
  {
    "level": 3,
    "code": 10204,
    "name": "Branca"
  },
  {
    "level": 3,
    "code": 10206,
    "name": "Ribeira de Fráguas"
  },
  {
    "level": 3,
    "code": 10209,
    "name": "Albergaria-a-Velha e Valmaior"
  },
  {
    "level": 3,
    "code": 10210,
    "name": "São João de Loure e Frossos"
  },
  {
    "level": 2,
    "code": 103,
    "name": "Anadia"
  },
  {
    "level": 3,
    "code": 10304,
    "name": "Avelãs de Caminho"
  },
  {
    "level": 3,
    "code": 10305,
    "name": "Avelãs de Cima"
  },
  {
    "level": 3,
    "code": 10307,
    "name": "Moita"
  },
  {
    "level": 3,
    "code": 10309,
    "name": "Sangalhos"
  },
  {
    "level": 3,
    "code": 10310,
    "name": "São Lourenço do Bairro"
  },
  {
    "level": 3,
    "code": 10312,
    "name": "Vila Nova de Monsarros"
  },
  {
    "level": 3,
    "code": 10313,
    "name": "Vilarinho do Bairro"
  },
  {
    "level": 3,
    "code": 10316,
    "name": "União das freguesias de Amoreira da Gândara, Paredes do Bairro e Ancas"
  },
  {
    "level": 3,
    "code": 10317,
    "name": "União das freguesias de Arcos e Mogofores"
  },
  {
    "level": 3,
    "code": 10318,
    "name": "União das freguesias de Tamengos, Aguim e Óis do Bairro"
  },
  {
    "level": 2,
    "code": 104,
    "name": "Arouca"
  },
  {
    "level": 3,
    "code": 10402,
    "name": "Alvarenga"
  },
  {
    "level": 3,
    "code": 10407,
    "name": "Chave"
  },
  {
    "level": 3,
    "code": 10409,
    "name": "Escariz"
  },
  {
    "level": 3,
    "code": 10411,
    "name": "Fermedo"
  },
  {
    "level": 3,
    "code": 10413,
    "name": "Mansores"
  },
  {
    "level": 3,
    "code": 10414,
    "name": "Moldes"
  },
  {
    "level": 3,
    "code": 10415,
    "name": "Rossas"
  },
  {
    "level": 3,
    "code": 10416,
    "name": "Santa Eulália"
  },
  {
    "level": 3,
    "code": 10417,
    "name": "São Miguel do Mato"
  },
  {
    "level": 3,
    "code": 10418,
    "name": "Tropeço"
  },
  {
    "level": 3,
    "code": 10419,
    "name": "Urrô"
  },
  {
    "level": 3,
    "code": 10420,
    "name": "Várzea"
  },
  {
    "level": 3,
    "code": 10421,
    "name": "União das freguesias de Arouca e Burgo"
  },
  {
    "level": 3,
    "code": 10422,
    "name": "União das freguesias de Cabreiros e Albergaria da Serra"
  },
  {
    "level": 3,
    "code": 10423,
    "name": "União das freguesias de Canelas e Espiunca"
  },
  {
    "level": 3,
    "code": 10424,
    "name": "União das freguesias de Covelo de Paivó e Janarde"
  },
  {
    "level": 2,
    "code": 105,
    "name": "Aveiro"
  },
  {
    "level": 3,
    "code": 10501,
    "name": "Aradas"
  },
  {
    "level": 3,
    "code": 10502,
    "name": "Cacia"
  },
  {
    "level": 3,
    "code": 10505,
    "name": "Esgueira"
  },
  {
    "level": 3,
    "code": 10508,
    "name": "Oliveirinha"
  },
  {
    "level": 3,
    "code": 10510,
    "name": "São Bernardo"
  },
  {
    "level": 3,
    "code": 10511,
    "name": "São Jacinto"
  },
  {
    "level": 3,
    "code": 10513,
    "name": "Santa Joana"
  },
  {
    "level": 3,
    "code": 10515,
    "name": "Eixo e Eirol"
  },
  {
    "level": 3,
    "code": 10516,
    "name": "Requeixo, Nossa Senhora de Fátima e Nariz"
  },
  {
    "level": 3,
    "code": 10517,
    "name": "União das freguesias de Glória e Vera Cruz"
  },
  {
    "level": 2,
    "code": 106,
    "name": "Castelo de Paiva"
  },
  {
    "level": 3,
    "code": 10602,
    "name": "Fornos"
  },
  {
    "level": 3,
    "code": 10606,
    "name": "Real"
  },
  {
    "level": 3,
    "code": 10607,
    "name": "Santa Maria de Sardoura"
  },
  {
    "level": 3,
    "code": 10608,
    "name": "São Martinho de Sardoura"
  },
  {
    "level": 3,
    "code": 10610,
    "name": "União das freguesias de Raiva, Pedorido e Paraíso"
  },
  {
    "level": 3,
    "code": 10611,
    "name": "União das freguesias de Sobrado e Bairros"
  },
  {
    "level": 2,
    "code": 107,
    "name": "Espinho"
  },
  {
    "level": 3,
    "code": 10702,
    "name": "Espinho"
  },
  {
    "level": 3,
    "code": 10704,
    "name": "Paramos"
  },
  {
    "level": 3,
    "code": 10705,
    "name": "Silvalde"
  },
  {
    "level": 3,
    "code": 10706,
    "name": "União das freguesias de Anta e Guetim"
  },
  {
    "level": 2,
    "code": 108,
    "name": "Estarreja"
  },
  {
    "level": 3,
    "code": 10801,
    "name": "Avanca"
  },
  {
    "level": 3,
    "code": 10805,
    "name": "Pardilhó"
  },
  {
    "level": 3,
    "code": 10806,
    "name": "Salreu"
  },
  {
    "level": 3,
    "code": 10808,
    "name": "União das freguesias de Beduído e Veiros"
  },
  {
    "level": 3,
    "code": 10809,
    "name": "União das freguesias de Canelas e Fermelã"
  },
  {
    "level": 2,
    "code": 109,
    "name": "Santa Maria da Feira"
  },
  {
    "level": 3,
    "code": 10901,
    "name": "Argoncilhe"
  },
  {
    "level": 3,
    "code": 10902,
    "name": "Arrifana"
  },
  {
    "level": 3,
    "code": 10904,
    "name": "Escapães"
  },
  {
    "level": 3,
    "code": 10907,
    "name": "Fiães"
  },
  {
    "level": 3,
    "code": 10908,
    "name": "Fornos"
  },
  {
    "level": 3,
    "code": 10913,
    "name": "Lourosa"
  },
  {
    "level": 3,
    "code": 10914,
    "name": "Milheirós de Poiares"
  },
  {
    "level": 3,
    "code": 10916,
    "name": "Mozelos"
  },
  {
    "level": 3,
    "code": 10917,
    "name": "Nogueira da Regedoura"
  },
  {
    "level": 3,
    "code": 10918,
    "name": "São Paio de Oleiros"
  },
  {
    "level": 3,
    "code": 10919,
    "name": "Paços de Brandão"
  },
  {
    "level": 3,
    "code": 10921,
    "name": "Rio Meão"
  },
  {
    "level": 3,
    "code": 10922,
    "name": "Romariz"
  },
  {
    "level": 3,
    "code": 10924,
    "name": "Sanguedo"
  },
  {
    "level": 3,
    "code": 10925,
    "name": "Santa Maria de Lamas"
  },
  {
    "level": 3,
    "code": 10926,
    "name": "São João de Ver"
  },
  {
    "level": 3,
    "code": 10932,
    "name": "União das freguesias de Caldas de São Jorge e Pigeiros"
  },
  {
    "level": 3,
    "code": 10933,
    "name": "União das freguesias de Canedo, Vale e Vila Maior"
  },
  {
    "level": 3,
    "code": 10934,
    "name": "União das freguesias de Lobão, Gião, Louredo e Guisande"
  },
  {
    "level": 3,
    "code": 10935,
    "name": "União das freguesias de Santa Maria da Feira, Travanca, Sanfins e Espargo"
  },
  {
    "level": 3,
    "code": 10936,
    "name": "União das freguesias de São Miguel do Souto e Mosteirô"
  },
  {
    "level": 2,
    "code": 110,
    "name": "Ílhavo"
  },
  {
    "level": 3,
    "code": 11005,
    "name": "Gafanha da Encarnação"
  },
  {
    "level": 3,
    "code": 11006,
    "name": "Gafanha da Nazaré"
  },
  {
    "level": 3,
    "code": 11007,
    "name": "Gafanha do Carmo"
  },
  {
    "level": 3,
    "code": 11008,
    "name": "Ílhavo (São Salvador)"
  },
  {
    "level": 2,
    "code": 111,
    "name": "Mealhada"
  },
  {
    "level": 3,
    "code": 11102,
    "name": "Barcouço"
  },
  {
    "level": 3,
    "code": 11103,
    "name": "Casal Comba"
  },
  {
    "level": 3,
    "code": 11104,
    "name": "Luso"
  },
  {
    "level": 3,
    "code": 11106,
    "name": "Pampilhosa"
  },
  {
    "level": 3,
    "code": 11107,
    "name": "Vacariça"
  },
  {
    "level": 3,
    "code": 11109,
    "name": "União das freguesias da Mealhada, Ventosa do Bairro e Antes"
  },
  {
    "level": 2,
    "code": 112,
    "name": "Murtosa"
  },
  {
    "level": 3,
    "code": 11201,
    "name": "Bunheiro"
  },
  {
    "level": 3,
    "code": 11202,
    "name": "Monte"
  },
  {
    "level": 3,
    "code": 11203,
    "name": "Murtosa"
  },
  {
    "level": 3,
    "code": 11204,
    "name": "Torreira"
  },
  {
    "level": 2,
    "code": 113,
    "name": "Oliveira de Azeméis"
  },
  {
    "level": 3,
    "code": 11301,
    "name": "Carregosa"
  },
  {
    "level": 3,
    "code": 11302,
    "name": "Cesar"
  },
  {
    "level": 3,
    "code": 11303,
    "name": "Fajões"
  },
  {
    "level": 3,
    "code": 11304,
    "name": "Loureiro"
  },
  {
    "level": 3,
    "code": 11305,
    "name": "Macieira de Sarnes"
  },
  {
    "level": 3,
    "code": 11310,
    "name": "Ossela"
  },
  {
    "level": 3,
    "code": 11315,
    "name": "São Martinho da Gândara"
  },
  {
    "level": 3,
    "code": 11318,
    "name": "São Roque"
  },
  {
    "level": 3,
    "code": 11319,
    "name": "Vila de Cucujães"
  },
  {
    "level": 3,
    "code": 11320,
    "name": "União das freguesias de Nogueira do Cravo e Pindelo"
  },
  {
    "level": 3,
    "code": 11321,
    "name": "União das freguesias de Oliveira de Azeméis, Santiago de Riba-Ul, Ul, Macinhata da Seixa e Madail"
  },
  {
    "level": 3,
    "code": 11322,
    "name": "União das freguesias de Pinheiro da Bemposta, Travanca e Palmaz"
  },
  {
    "level": 2,
    "code": 114,
    "name": "Oliveira do Bairro"
  },
  {
    "level": 3,
    "code": 11403,
    "name": "Oiã"
  },
  {
    "level": 3,
    "code": 11404,
    "name": "Oliveira do Bairro"
  },
  {
    "level": 3,
    "code": 11405,
    "name": "Palhaça"
  },
  {
    "level": 3,
    "code": 11407,
    "name": "União das freguesias de Bustos, Troviscal e Mamarrosa"
  },
  {
    "level": 2,
    "code": 115,
    "name": "Ovar"
  },
  {
    "level": 3,
    "code": 11502,
    "name": "Cortegaça"
  },
  {
    "level": 3,
    "code": 11503,
    "name": "Esmoriz"
  },
  {
    "level": 3,
    "code": 11504,
    "name": "Maceda"
  },
  {
    "level": 3,
    "code": 11507,
    "name": "Válega"
  },
  {
    "level": 3,
    "code": 11509,
    "name": "União das freguesias de Ovar, São João, Arada e São Vicente de Pereira Jusã"
  },
  {
    "level": 2,
    "code": 116,
    "name": "São João da Madeira"
  },
  {
    "level": 3,
    "code": 11601,
    "name": "São João da Madeira"
  },
  {
    "level": 2,
    "code": 117,
    "name": "Sever do Vouga"
  },
  {
    "level": 3,
    "code": 11702,
    "name": "Couto de Esteves"
  },
  {
    "level": 3,
    "code": 11704,
    "name": "Pessegueiro do Vouga"
  },
  {
    "level": 3,
    "code": 11705,
    "name": "Rocas do Vouga"
  },
  {
    "level": 3,
    "code": 11706,
    "name": "Sever do Vouga"
  },
  {
    "level": 3,
    "code": 11708,
    "name": "Talhadas"
  },
  {
    "level": 3,
    "code": 11710,
    "name": "União das freguesias de Cedrim e Paradela"
  },
  {
    "level": 3,
    "code": 11711,
    "name": "União das freguesias de Silva Escura e Dornelas"
  },
  {
    "level": 2,
    "code": 118,
    "name": "Vagos"
  },
  {
    "level": 3,
    "code": 11801,
    "name": "Calvão"
  },
  {
    "level": 3,
    "code": 11804,
    "name": "Gafanha da Boa Hora"
  },
  {
    "level": 3,
    "code": 11805,
    "name": "Ouca"
  },
  {
    "level": 3,
    "code": 11807,
    "name": "Sosa"
  },
  {
    "level": 3,
    "code": 11810,
    "name": "Santo André de Vagos"
  },
  {
    "level": 3,
    "code": 11812,
    "name": "União das freguesias de Fonte de Angeão e Covão do Lobo"
  },
  {
    "level": 3,
    "code": 11813,
    "name": "União das freguesias de Ponte de Vagos e Santa Catarina"
  },
  {
    "level": 3,
    "code": 11814,
    "name": "União das freguesias de Vagos e Santo António"
  },
  {
    "level": 2,
    "code": 119,
    "name": "Vale de Cambra"
  },
  {
    "level": 3,
    "code": 11901,
    "name": "Arões"
  },
  {
    "level": 3,
    "code": 11902,
    "name": "São Pedro de Castelões"
  },
  {
    "level": 3,
    "code": 11903,
    "name": "Cepelos"
  },
  {
    "level": 3,
    "code": 11905,
    "name": "Junqueira"
  },
  {
    "level": 3,
    "code": 11906,
    "name": "Macieira de Cambra"
  },
  {
    "level": 3,
    "code": 11907,
    "name": "Roge"
  },
  {
    "level": 3,
    "code": 11910,
    "name": "União das freguesias de Vila Chã, Codal e Vila Cova de Perrinho"
  },
  {
    "level": 1,
    "code": 2,
    "name": "Beja"
  },
  {
    "level": 2,
    "code": 201,
    "name": "Aljustrel"
  },
  {
    "level": 3,
    "code": 20102,
    "name": "Ervidel"
  },
  {
    "level": 3,
    "code": 20103,
    "name": "Messejana"
  },
  {
    "level": 3,
    "code": 20104,
    "name": "São João de Negrilhos"
  },
  {
    "level": 3,
    "code": 20106,
    "name": "União das freguesias de Aljustrel e Rio de Moinhos"
  },
  {
    "level": 2,
    "code": 202,
    "name": "Almodôvar"
  },
  {
    "level": 3,
    "code": 20203,
    "name": "Rosário"
  },
  {
    "level": 3,
    "code": 20205,
    "name": "Santa Cruz"
  },
  {
    "level": 3,
    "code": 20206,
    "name": "São Barnabé"
  },
  {
    "level": 3,
    "code": 20208,
    "name": "Aldeia dos Fernandes"
  },
  {
    "level": 3,
    "code": 20209,
    "name": "União das freguesias de Almodôvar e Graça dos Padrões"
  },
  {
    "level": 3,
    "code": 20210,
    "name": "União das freguesias de Santa Clara-a-Nova e Gomes Aires"
  },
  {
    "level": 2,
    "code": 203,
    "name": "Alvito"
  },
  {
    "level": 3,
    "code": 20301,
    "name": "Alvito"
  },
  {
    "level": 3,
    "code": 20302,
    "name": "Vila Nova da Baronia"
  },
  {
    "level": 2,
    "code": 204,
    "name": "Barrancos"
  },
  {
    "level": 3,
    "code": 20401,
    "name": "Barrancos"
  },
  {
    "level": 2,
    "code": 205,
    "name": "Beja"
  },
  {
    "level": 3,
    "code": 20502,
    "name": "Baleizão"
  },
  {
    "level": 3,
    "code": 20503,
    "name": "Beringel"
  },
  {
    "level": 3,
    "code": 20504,
    "name": "Cabeça Gorda"
  },
  {
    "level": 3,
    "code": 20506,
    "name": "Nossa Senhora das Neves"
  },
  {
    "level": 3,
    "code": 20510,
    "name": "Santa Clara de Louredo"
  },
  {
    "level": 3,
    "code": 20516,
    "name": "São Matias"
  },
  {
    "level": 3,
    "code": 20519,
    "name": "União das freguesias de Albernoa e Trindade"
  },
  {
    "level": 3,
    "code": 20520,
    "name": "União das freguesias de Beja (Salvador e Santa Maria da Feira)"
  },
  {
    "level": 3,
    "code": 20521,
    "name": "União das freguesias de Beja (Santiago Maior e São João Baptista)"
  },
  {
    "level": 3,
    "code": 20522,
    "name": "União das freguesias de Salvada e Quintos"
  },
  {
    "level": 3,
    "code": 20523,
    "name": "União das freguesias de Santa Vitória e Mombeja"
  },
  {
    "level": 3,
    "code": 20524,
    "name": "União das freguesias de Trigaches e São Brissos"
  },
  {
    "level": 2,
    "code": 206,
    "name": "Castro Verde"
  },
  {
    "level": 3,
    "code": 20603,
    "name": "Entradas"
  },
  {
    "level": 3,
    "code": 20604,
    "name": "Santa Bárbara de Padrões"
  },
  {
    "level": 3,
    "code": 20605,
    "name": "São Marcos da Ataboeira"
  },
  {
    "level": 3,
    "code": 20606,
    "name": "União das freguesias de Castro Verde e Casével"
  },
  {
    "level": 2,
    "code": 207,
    "name": "Cuba"
  },
  {
    "level": 3,
    "code": 20701,
    "name": "Cuba"
  },
  {
    "level": 3,
    "code": 20702,
    "name": "Faro do Alentejo"
  },
  {
    "level": 3,
    "code": 20703,
    "name": "Vila Alva"
  },
  {
    "level": 3,
    "code": 20704,
    "name": "Vila Ruiva"
  },
  {
    "level": 2,
    "code": 208,
    "name": "Ferreira do Alentejo"
  },
  {
    "level": 3,
    "code": 20803,
    "name": "Figueira dos Cavaleiros"
  },
  {
    "level": 3,
    "code": 20804,
    "name": "Odivelas"
  },
  {
    "level": 3,
    "code": 20807,
    "name": "União das freguesias de Alfundão e Peroguarda"
  },
  {
    "level": 3,
    "code": 20808,
    "name": "União das freguesias de Ferreira do Alentejo e Canhestros"
  },
  {
    "level": 2,
    "code": 209,
    "name": "Mértola"
  },
  {
    "level": 3,
    "code": 20901,
    "name": "Alcaria Ruiva"
  },
  {
    "level": 3,
    "code": 20902,
    "name": "Corte do Pinto"
  },
  {
    "level": 3,
    "code": 20903,
    "name": "Espírito Santo"
  },
  {
    "level": 3,
    "code": 20904,
    "name": "Mértola"
  },
  {
    "level": 3,
    "code": 20905,
    "name": "Santana de Cambas"
  },
  {
    "level": 3,
    "code": 20906,
    "name": "São João dos Caldeireiros"
  },
  {
    "level": 3,
    "code": 20910,
    "name": "União das freguesias de São Miguel do Pinheiro, São Pedro de Solis e São Sebastião dos Carros"
  },
  {
    "level": 2,
    "code": 210,
    "name": "Moura"
  },
  {
    "level": 3,
    "code": 21001,
    "name": "Amareleja"
  },
  {
    "level": 3,
    "code": 21002,
    "name": "Póvoa de São Miguel"
  },
  {
    "level": 3,
    "code": 21008,
    "name": "Sobral da Adiça"
  },
  {
    "level": 3,
    "code": 21009,
    "name": "União das freguesias de Moura (Santo Agostinho e São João Baptista) e Santo Amador"
  },
  {
    "level": 3,
    "code": 21010,
    "name": "União das freguesias de Safara e Santo Aleixo da Restauração"
  },
  {
    "level": 2,
    "code": 211,
    "name": "Odemira"
  },
  {
    "level": 3,
    "code": 21102,
    "name": "Relíquias"
  },
  {
    "level": 3,
    "code": 21103,
    "name": "Sabóia"
  },
  {
    "level": 3,
    "code": 21106,
    "name": "São Luís"
  },
  {
    "level": 3,
    "code": 21107,
    "name": "São Martinho das Amoreiras"
  },
  {
    "level": 3,
    "code": 21111,
    "name": "Vila Nova de Milfontes"
  },
  {
    "level": 3,
    "code": 21115,
    "name": "Luzianes-Gare"
  },
  {
    "level": 3,
    "code": 21116,
    "name": "Boavista dos Pinheiros"
  },
  {
    "level": 3,
    "code": 21117,
    "name": "Longueira/Almograve"
  },
  {
    "level": 3,
    "code": 21118,
    "name": "Colos"
  },
  {
    "level": 3,
    "code": 21119,
    "name": "Santa Clara-a-Velha"
  },
  {
    "level": 3,
    "code": 21120,
    "name": "São Salvador e Santa Maria"
  },
  {
    "level": 3,
    "code": 21121,
    "name": "São Teotónio"
  },
  {
    "level": 3,
    "code": 21122,
    "name": "Vale de Santiago"
  },
  {
    "level": 2,
    "code": 212,
    "name": "Ourique"
  },
  {
    "level": 3,
    "code": 21203,
    "name": "Ourique"
  },
  {
    "level": 3,
    "code": 21206,
    "name": "Santana da Serra"
  },
  {
    "level": 3,
    "code": 21207,
    "name": "União das freguesias de Garvão e Santa Luzia"
  },
  {
    "level": 3,
    "code": 21208,
    "name": "União das freguesias de Panoias e Conceição"
  },
  {
    "level": 2,
    "code": 213,
    "name": "Serpa"
  },
  {
    "level": 3,
    "code": 21302,
    "name": "Brinches"
  },
  {
    "level": 3,
    "code": 21303,
    "name": "Pias"
  },
  {
    "level": 3,
    "code": 21307,
    "name": "Vila Verde de Ficalho"
  },
  {
    "level": 3,
    "code": 21308,
    "name": "União das freguesias de Serpa (Salvador e Santa Maria)"
  },
  {
    "level": 3,
    "code": 21309,
    "name": "União das freguesias de Vila Nova de São Bento e Vale de Vargo"
  },
  {
    "level": 2,
    "code": 214,
    "name": "Vidigueira"
  },
  {
    "level": 3,
    "code": 21401,
    "name": "Pedrógão"
  },
  {
    "level": 3,
    "code": 21402,
    "name": "Selmes"
  },
  {
    "level": 3,
    "code": 21403,
    "name": "Vidigueira"
  },
  {
    "level": 3,
    "code": 21404,
    "name": "Vila de Frades"
  },
  {
    "level": 1,
    "code": 3,
    "name": "Braga"
  },
  {
    "level": 2,
    "code": 301,
    "name": "Amares"
  },
  {
    "level": 3,
    "code": 30102,
    "name": "Barreiros"
  },
  {
    "level": 3,
    "code": 30104,
    "name": "Bico"
  },
  {
    "level": 3,
    "code": 30105,
    "name": "Caires"
  },
  {
    "level": 3,
    "code": 30107,
    "name": "Carrazedo"
  },
  {
    "level": 3,
    "code": 30108,
    "name": "Dornelas"
  },
  {
    "level": 3,
    "code": 30111,
    "name": "Fiscal"
  },
  {
    "level": 3,
    "code": 30112,
    "name": "Goães"
  },
  {
    "level": 3,
    "code": 30113,
    "name": "Lago"
  },
  {
    "level": 3,
    "code": 30118,
    "name": "Rendufe"
  },
  {
    "level": 3,
    "code": 30119,
    "name": "Bouro (Santa Maria)"
  },
  {
    "level": 3,
    "code": 30120,
    "name": "Bouro (Santa Marta)"
  },
  {
    "level": 3,
    "code": 30125,
    "name": "União das freguesias de Amares e Figueiredo"
  },
  {
    "level": 3,
    "code": 30126,
    "name": "União das freguesias de Caldelas, Sequeiros e Paranhos"
  },
  {
    "level": 3,
    "code": 30127,
    "name": "União das freguesias de Ferreiros, Prozelo e Besteiros"
  },
  {
    "level": 3,
    "code": 30128,
    "name": "União das freguesias de Torre e Portela"
  },
  {
    "level": 3,
    "code": 30129,
    "name": "União das freguesias de Vilela, Seramil e Paredes Secas"
  },
  {
    "level": 2,
    "code": 302,
    "name": "Barcelos"
  },
  {
    "level": 3,
    "code": 30201,
    "name": "Abade de Neiva"
  },
  {
    "level": 3,
    "code": 30202,
    "name": "Aborim"
  },
  {
    "level": 3,
    "code": 30203,
    "name": "Adães"
  },
  {
    "level": 3,
    "code": 30205,
    "name": "Airó"
  },
  {
    "level": 3,
    "code": 30206,
    "name": "Aldreu"
  },
  {
    "level": 3,
    "code": 30208,
    "name": "Alvelos"
  },
  {
    "level": 3,
    "code": 30209,
    "name": "Arcozelo"
  },
  {
    "level": 3,
    "code": 30210,
    "name": "Areias"
  },
  {
    "level": 3,
    "code": 30212,
    "name": "Balugães"
  },
  {
    "level": 3,
    "code": 30213,
    "name": "Barcelinhos"
  },
  {
    "level": 3,
    "code": 30215,
    "name": "Barqueiros"
  },
  {
    "level": 3,
    "code": 30216,
    "name": "Cambeses"
  },
  {
    "level": 3,
    "code": 30218,
    "name": "Carapeços"
  },
  {
    "level": 3,
    "code": 30220,
    "name": "Carvalhal"
  },
  {
    "level": 3,
    "code": 30221,
    "name": "Carvalhas"
  },
  {
    "level": 3,
    "code": 30224,
    "name": "Cossourado"
  },
  {
    "level": 3,
    "code": 30228,
    "name": "Cristelo"
  },
  {
    "level": 3,
    "code": 30234,
    "name": "Fornelos"
  },
  {
    "level": 3,
    "code": 30235,
    "name": "Fragoso"
  },
  {
    "level": 3,
    "code": 30237,
    "name": "Gilmonde"
  },
  {
    "level": 3,
    "code": 30242,
    "name": "Lama"
  },
  {
    "level": 3,
    "code": 30243,
    "name": "Lijó"
  },
  {
    "level": 3,
    "code": 30244,
    "name": "Macieira de Rates"
  },
  {
    "level": 3,
    "code": 30245,
    "name": "Manhente"
  },
  {
    "level": 3,
    "code": 30247,
    "name": "Martim"
  },
  {
    "level": 3,
    "code": 30252,
    "name": "Moure"
  },
  {
    "level": 3,
    "code": 30254,
    "name": "Oliveira"
  },
  {
    "level": 3,
    "code": 30255,
    "name": "Palme"
  },
  {
    "level": 3,
    "code": 30256,
    "name": "Panque"
  },
  {
    "level": 3,
    "code": 30257,
    "name": "Paradela"
  },
  {
    "level": 3,
    "code": 30259,
    "name": "Pereira"
  },
  {
    "level": 3,
    "code": 30260,
    "name": "Perelhal"
  },
  {
    "level": 3,
    "code": 30261,
    "name": "Pousa"
  },
  {
    "level": 3,
    "code": 30263,
    "name": "Remelhe"
  },
  {
    "level": 3,
    "code": 30264,
    "name": "Roriz"
  },
  {
    "level": 3,
    "code": 30265,
    "name": "Rio Covo (Santa Eugénia)"
  },
  {
    "level": 3,
    "code": 30268,
    "name": "Galegos (Santa Maria)"
  },
  {
    "level": 3,
    "code": 30272,
    "name": "Galegos (São Martinho)"
  },
  {
    "level": 3,
    "code": 30277,
    "name": "Tamel (São Veríssimo)"
  },
  {
    "level": 3,
    "code": 30279,
    "name": "Silva"
  },
  {
    "level": 3,
    "code": 30282,
    "name": "Ucha"
  },
  {
    "level": 3,
    "code": 30283,
    "name": "Várzea"
  },
  {
    "level": 3,
    "code": 30287,
    "name": "Vila Seca"
  },
  {
    "level": 3,
    "code": 30290,
    "name": "União das freguesias de Alheira e Igreja Nova"
  },
  {
    "level": 3,
    "code": 30291,
    "name": "União das freguesias de Alvito (São Pedro e São Martinho) e Couto"
  },
  {
    "level": 3,
    "code": 30292,
    "name": "União das freguesias de Areias de Vilar e Encourados"
  },
  {
    "level": 3,
    "code": 30293,
    "name": "União das freguesias de Barcelos, Vila Boa e Vila Frescainha (São Martinho e São Pedro)"
  },
  {
    "level": 3,
    "code": 30294,
    "name": "União das freguesias de Campo e Tamel (São Pedro Fins)"
  },
  {
    "level": 3,
    "code": 30295,
    "name": "União das freguesias de Carreira e Fonte Coberta"
  },
  {
    "level": 3,
    "code": 30296,
    "name": "União das freguesias de Chorente, Góios, Courel, Pedra Furada e Gueral"
  },
  {
    "level": 3,
    "code": 30297,
    "name": "União das freguesias de Creixomil e Mariz"
  },
  {
    "level": 3,
    "code": 30298,
    "name": "União das freguesias de Durrães e Tregosa"
  },
  {
    "level": 3,
    "code": 30299,
    "name": "União das freguesias de Gamil e Midões"
  },
  {
    "level": 3,
    "code": "0302FA",
    "name": "União das freguesias de Milhazes, Vilar de Figos e Faria"
  },
  {
    "level": 3,
    "code": "0302FB",
    "name": "União das freguesias de Negreiros e Chavão"
  },
  {
    "level": 3,
    "code": "0302FC",
    "name": "União das freguesias de Quintiães e Aguiar"
  },
  {
    "level": 3,
    "code": "0302FD",
    "name": "União das freguesias de Sequeade e Bastuço (São João e Santo Estevão)"
  },
  {
    "level": 3,
    "code": "0302FE",
    "name": "União das freguesias de Silveiros e Rio Covo (Santa Eulália)"
  },
  {
    "level": 3,
    "code": "0302FF",
    "name": "União das freguesias de Tamel (Santa Leocádia) e Vilar do Monte"
  },
  {
    "level": 3,
    "code": "0302FG",
    "name": "União das freguesias de Viatodos, Grimancelos, Minhotães e Monte de Fralães"
  },
  {
    "level": 3,
    "code": "0302FH",
    "name": "União das freguesias de Vila Cova e Feitos"
  },
  {
    "level": 2,
    "code": 303,
    "name": "Braga"
  },
  {
    "level": 3,
    "code": 30301,
    "name": "Adaúfe"
  },
  {
    "level": 3,
    "code": 30312,
    "name": "Espinho"
  },
  {
    "level": 3,
    "code": 30313,
    "name": "Esporões"
  },
  {
    "level": 3,
    "code": 30315,
    "name": "Figueiredo"
  },
  {
    "level": 3,
    "code": 30319,
    "name": "Gualtar"
  },
  {
    "level": 3,
    "code": 30322,
    "name": "Lamas"
  },
  {
    "level": 3,
    "code": 30325,
    "name": "Mire de Tibães"
  },
  {
    "level": 3,
    "code": 30330,
    "name": "Padim da Graça"
  },
  {
    "level": 3,
    "code": 30331,
    "name": "Palmeira"
  },
  {
    "level": 3,
    "code": 30334,
    "name": "Pedralva"
  },
  {
    "level": 3,
    "code": 30336,
    "name": "Priscos"
  },
  {
    "level": 3,
    "code": 30338,
    "name": "Ruilhe"
  },
  {
    "level": 3,
    "code": 30349,
    "name": "Braga (São Vicente)"
  },
  {
    "level": 3,
    "code": 30351,
    "name": "Braga (São Vítor)"
  },
  {
    "level": 3,
    "code": 30354,
    "name": "Sequeira"
  },
  {
    "level": 3,
    "code": 30355,
    "name": "Sobreposta"
  },
  {
    "level": 3,
    "code": 30356,
    "name": "Tadim"
  },
  {
    "level": 3,
    "code": 30357,
    "name": "Tebosa"
  },
  {
    "level": 3,
    "code": 30363,
    "name": "União das freguesias de Arentim e Cunha"
  },
  {
    "level": 3,
    "code": 30364,
    "name": "União das freguesias de Braga (Maximinos, Sé e Cividade)"
  },
  {
    "level": 3,
    "code": 30365,
    "name": "União das freguesias de Braga (São José de São Lázaro e São João do Souto)"
  },
  {
    "level": 3,
    "code": 30366,
    "name": "União das freguesias de Cabreiros e Passos (São Julião)"
  },
  {
    "level": 3,
    "code": 30367,
    "name": "União das freguesias de Celeirós, Aveleda e Vimieiro"
  },
  {
    "level": 3,
    "code": 30368,
    "name": "União das freguesias de Crespos e Pousada"
  },
  {
    "level": 3,
    "code": 30369,
    "name": "União das freguesias de Escudeiros e Penso (Santo Estêvão e São Vicente)"
  },
  {
    "level": 3,
    "code": 30370,
    "name": "União das freguesias de Este (São Pedro e São Mamede)"
  },
  {
    "level": 3,
    "code": 30371,
    "name": "União das freguesias de Ferreiros e Gondizalves"
  },
  {
    "level": 3,
    "code": 30372,
    "name": "União das freguesias de Guisande e Oliveira (São Pedro)"
  },
  {
    "level": 3,
    "code": 30373,
    "name": "União das freguesias de Lomar e Arcos"
  },
  {
    "level": 3,
    "code": 30374,
    "name": "União das freguesias de Merelim (São Paio), Panoias e Parada de Tibães"
  },
  {
    "level": 3,
    "code": 30375,
    "name": "União das freguesias de Merelim (São Pedro) e Frossos"
  },
  {
    "level": 3,
    "code": 30376,
    "name": "União das freguesias de Morreira e Trandeiras"
  },
  {
    "level": 3,
    "code": 30377,
    "name": "União das freguesias de Nogueira, Fraião e Lamaçães"
  },
  {
    "level": 3,
    "code": 30378,
    "name": "União das freguesias de Nogueiró e Tenões"
  },
  {
    "level": 3,
    "code": 30379,
    "name": "União das freguesias de Real, Dume e Semelhe"
  },
  {
    "level": 3,
    "code": 30380,
    "name": "União das freguesias de Santa Lucrécia de Algeriz e Navarra"
  },
  {
    "level": 3,
    "code": 30381,
    "name": "União das freguesias de Vilaça e Fradelos"
  },
  {
    "level": 2,
    "code": 304,
    "name": "Cabeceiras de Basto"
  },
  {
    "level": 3,
    "code": 30401,
    "name": "Abadim"
  },
  {
    "level": 3,
    "code": 30404,
    "name": "Basto"
  },
  {
    "level": 3,
    "code": 30405,
    "name": "Bucos"
  },
  {
    "level": 3,
    "code": 30406,
    "name": "Cabeceiras de Basto"
  },
  {
    "level": 3,
    "code": 30407,
    "name": "Cavez"
  },
  {
    "level": 3,
    "code": 30408,
    "name": "Faia"
  },
  {
    "level": 3,
    "code": 30413,
    "name": "Pedraça"
  },
  {
    "level": 3,
    "code": 30415,
    "name": "Rio Douro"
  },
  {
    "level": 3,
    "code": 30418,
    "name": "União das freguesias de Alvite e Passos"
  },
  {
    "level": 3,
    "code": 30419,
    "name": "União das freguesias de Arco de Baúlhe e Vila Nune"
  },
  {
    "level": 3,
    "code": 30420,
    "name": "União das freguesias de Gondiães e Vilar de Cunhas"
  },
  {
    "level": 3,
    "code": 30421,
    "name": "União das freguesias de Refojos de Basto, Outeiro e Painzela"
  },
  {
    "level": 2,
    "code": 305,
    "name": "Celorico de Basto"
  },
  {
    "level": 3,
    "code": 30501,
    "name": "Agilde"
  },
  {
    "level": 3,
    "code": 30502,
    "name": "Arnóia"
  },
  {
    "level": 3,
    "code": 30503,
    "name": "Borba de Montanha"
  },
  {
    "level": 3,
    "code": 30508,
    "name": "Codeçoso"
  },
  {
    "level": 3,
    "code": 30510,
    "name": "Fervença"
  },
  {
    "level": 3,
    "code": 30515,
    "name": "Moreira do Castelo"
  },
  {
    "level": 3,
    "code": 30517,
    "name": "Rego"
  },
  {
    "level": 3,
    "code": 30518,
    "name": "Ribas"
  },
  {
    "level": 3,
    "code": 30520,
    "name": "Basto (São Clemente)"
  },
  {
    "level": 3,
    "code": 30521,
    "name": "Vale de Bouro"
  },
  {
    "level": 3,
    "code": 30523,
    "name": "União das freguesias de Britelo, Gémeos e Ourilhe"
  },
  {
    "level": 3,
    "code": 30524,
    "name": "União das freguesias de Caçarilhe e Infesta"
  },
  {
    "level": 3,
    "code": 30525,
    "name": "União das freguesias de Canedo de Basto e Corgo"
  },
  {
    "level": 3,
    "code": 30526,
    "name": "União das freguesias de Carvalho e Basto (Santa Tecla)"
  },
  {
    "level": 3,
    "code": 30527,
    "name": "União das freguesias de Veade, Gagos e Molares"
  },
  {
    "level": 2,
    "code": 306,
    "name": "Esposende"
  },
  {
    "level": 3,
    "code": 30601,
    "name": "Antas"
  },
  {
    "level": 3,
    "code": 30608,
    "name": "Forjães"
  },
  {
    "level": 3,
    "code": 30610,
    "name": "Gemeses"
  },
  {
    "level": 3,
    "code": 30615,
    "name": "Vila Chã"
  },
  {
    "level": 3,
    "code": 30616,
    "name": "União das freguesias de Apúlia e Fão"
  },
  {
    "level": 3,
    "code": 30617,
    "name": "União das freguesias de Belinho e Mar"
  },
  {
    "level": 3,
    "code": 30618,
    "name": "União das freguesias de Esposende, Marinhas e Gandra"
  },
  {
    "level": 3,
    "code": 30619,
    "name": "União das freguesias de Fonte Boa e Rio Tinto"
  },
  {
    "level": 3,
    "code": 30620,
    "name": "União das freguesias de Palmeira de Faro e Curvos"
  },
  {
    "level": 2,
    "code": 307,
    "name": "Fafe"
  },
  {
    "level": 3,
    "code": 30705,
    "name": "Armil"
  },
  {
    "level": 3,
    "code": 30708,
    "name": "Estorãos"
  },
  {
    "level": 3,
    "code": 30709,
    "name": "Fafe"
  },
  {
    "level": 3,
    "code": 30712,
    "name": "Fornelos"
  },
  {
    "level": 3,
    "code": 30714,
    "name": "Golães"
  },
  {
    "level": 3,
    "code": 30716,
    "name": "Medelo"
  },
  {
    "level": 3,
    "code": 30719,
    "name": "Passos"
  },
  {
    "level": 3,
    "code": 30722,
    "name": "Quinchães"
  },
  {
    "level": 3,
    "code": 30723,
    "name": "Regadas"
  },
  {
    "level": 3,
    "code": 30724,
    "name": "Revelhe"
  },
  {
    "level": 3,
    "code": 30725,
    "name": "Ribeiros"
  },
  {
    "level": 3,
    "code": 30726,
    "name": "Arões (Santa Cristina)"
  },
  {
    "level": 3,
    "code": 30728,
    "name": "São Gens"
  },
  {
    "level": 3,
    "code": 30729,
    "name": "Silvares (São Martinho)"
  },
  {
    "level": 3,
    "code": 30730,
    "name": "Arões (São Romão)"
  },
  {
    "level": 3,
    "code": 30733,
    "name": "Travassós"
  },
  {
    "level": 3,
    "code": 30736,
    "name": "Vinhós"
  },
  {
    "level": 3,
    "code": 30737,
    "name": "União de freguesias de Aboim, Felgueiras, Gontim e Pedraído"
  },
  {
    "level": 3,
    "code": 30738,
    "name": "União de freguesias de Agrela e Serafão"
  },
  {
    "level": 3,
    "code": 30739,
    "name": "União de freguesias de Antime e Silvares (São Clemente)"
  },
  {
    "level": 3,
    "code": 30740,
    "name": "União de freguesias de Ardegão, Arnozela e Seidões"
  },
  {
    "level": 3,
    "code": 30741,
    "name": "União de freguesias de Cepães e Fareja"
  },
  {
    "level": 3,
    "code": 30742,
    "name": "União de freguesias de Freitas e Vila Cova"
  },
  {
    "level": 3,
    "code": 30743,
    "name": "União de freguesias de Monte e Queimadela"
  },
  {
    "level": 3,
    "code": 30744,
    "name": "União de freguesias de Moreira do Rei e Várzea Cova"
  },
  {
    "level": 2,
    "code": 308,
    "name": "Guimarães"
  },
  {
    "level": 3,
    "code": 30801,
    "name": "Aldão"
  },
  {
    "level": 3,
    "code": 30804,
    "name": "Azurém"
  },
  {
    "level": 3,
    "code": 30806,
    "name": "Barco"
  },
  {
    "level": 3,
    "code": 30807,
    "name": "Brito"
  },
  {
    "level": 3,
    "code": 30808,
    "name": "Caldelas"
  },
  {
    "level": 3,
    "code": 30812,
    "name": "Costa"
  },
  {
    "level": 3,
    "code": 30813,
    "name": "Creixomil"
  },
  {
    "level": 3,
    "code": 30815,
    "name": "Fermentões"
  },
  {
    "level": 3,
    "code": 30820,
    "name": "Gonça"
  },
  {
    "level": 3,
    "code": 30821,
    "name": "Gondar"
  },
  {
    "level": 3,
    "code": 30823,
    "name": "Guardizela"
  },
  {
    "level": 3,
    "code": 30824,
    "name": "Infantas"
  },
  {
    "level": 3,
    "code": 30827,
    "name": "Longos"
  },
  {
    "level": 3,
    "code": 30828,
    "name": "Lordelo"
  },
  {
    "level": 3,
    "code": 30830,
    "name": "Mesão Frio"
  },
  {
    "level": 3,
    "code": 30831,
    "name": "Moreira de Cónegos"
  },
  {
    "level": 3,
    "code": 30832,
    "name": "Nespereira"
  },
  {
    "level": 3,
    "code": 30835,
    "name": "Pencelo"
  },
  {
    "level": 3,
    "code": 30836,
    "name": "Pinheiro"
  },
  {
    "level": 3,
    "code": 30837,
    "name": "Polvoreira"
  },
  {
    "level": 3,
    "code": 30838,
    "name": "Ponte"
  },
  {
    "level": 3,
    "code": 30840,
    "name": "Ronfe"
  },
  {
    "level": 3,
    "code": 30842,
    "name": "Prazins (Santa Eufémia)"
  },
  {
    "level": 3,
    "code": 30850,
    "name": "Selho (São Cristóvão)"
  },
  {
    "level": 3,
    "code": 30854,
    "name": "Selho (São Jorge)"
  },
  {
    "level": 3,
    "code": 30857,
    "name": "Candoso (São Martinho)"
  },
  {
    "level": 3,
    "code": 30858,
    "name": "Sande (São Martinho)"
  },
  {
    "level": 3,
    "code": 30865,
    "name": "São Torcato"
  },
  {
    "level": 3,
    "code": 30866,
    "name": "Serzedelo"
  },
  {
    "level": 3,
    "code": 30868,
    "name": "Silvares"
  },
  {
    "level": 3,
    "code": 30871,
    "name": "Urgezes"
  },
  {
    "level": 3,
    "code": 30875,
    "name": "União das freguesias de Abação e Gémeos"
  },
  {
    "level": 3,
    "code": 30876,
    "name": "União das freguesias de Airão Santa Maria, Airão São João e Vermil"
  },
  {
    "level": 3,
    "code": 30877,
    "name": "União das freguesias de Arosa e Castelões"
  },
  {
    "level": 3,
    "code": 30878,
    "name": "União das freguesias de Atães e Rendufe"
  },
  {
    "level": 3,
    "code": 30879,
    "name": "União das freguesias de Briteiros Santo Estêvão e Donim"
  },
  {
    "level": 3,
    "code": 30880,
    "name": "União das freguesias de Briteiros São Salvador e Briteiros Santa Leocádia"
  },
  {
    "level": 3,
    "code": 30881,
    "name": "União das freguesias de Candoso São Tiago e Mascotelos"
  },
  {
    "level": 3,
    "code": 30882,
    "name": "União das freguesias de Conde e Gandarela"
  },
  {
    "level": 3,
    "code": 30883,
    "name": "União das freguesias de Leitões, Oleiros e Figueiredo"
  },
  {
    "level": 3,
    "code": 30884,
    "name": "União das freguesias de Oliveira, São Paio e São Sebastião"
  },
  {
    "level": 3,
    "code": 30885,
    "name": "União das freguesias de Prazins Santo Tirso e Corvite"
  },
  {
    "level": 3,
    "code": 30886,
    "name": "União das freguesias de Sande São Lourenço e Balazar"
  },
  {
    "level": 3,
    "code": 30887,
    "name": "União das freguesias de Sande Vila Nova e Sande São Clemente"
  },
  {
    "level": 3,
    "code": 30888,
    "name": "União das freguesias de Selho São Lourenço e Gominhães"
  },
  {
    "level": 3,
    "code": 30889,
    "name": "União das freguesias de Serzedo e Calvos"
  },
  {
    "level": 3,
    "code": 30890,
    "name": "União das freguesias de Souto Santa Maria, Souto São Salvador e Gondomar"
  },
  {
    "level": 3,
    "code": 30891,
    "name": "União das freguesias de Tabuadelo e São Faustino"
  },
  {
    "level": 2,
    "code": 309,
    "name": "Póvoa de Lanhoso"
  },
  {
    "level": 3,
    "code": 30906,
    "name": "Covelas"
  },
  {
    "level": 3,
    "code": 30908,
    "name": "Ferreiros"
  },
  {
    "level": 3,
    "code": 30912,
    "name": "Galegos"
  },
  {
    "level": 3,
    "code": 30913,
    "name": "Garfe"
  },
  {
    "level": 3,
    "code": 30914,
    "name": "Geraz do Minho"
  },
  {
    "level": 3,
    "code": 30915,
    "name": "Lanhoso"
  },
  {
    "level": 3,
    "code": 30917,
    "name": "Monsul"
  },
  {
    "level": 3,
    "code": 30919,
    "name": "Póvoa de Lanhoso (Nossa Senhora do Amparo)"
  },
  {
    "level": 3,
    "code": 30921,
    "name": "Rendufinho"
  },
  {
    "level": 3,
    "code": 30922,
    "name": "Santo Emilião"
  },
  {
    "level": 3,
    "code": 30923,
    "name": "São João de Rei"
  },
  {
    "level": 3,
    "code": 30924,
    "name": "Serzedelo"
  },
  {
    "level": 3,
    "code": 30925,
    "name": "Sobradelo da Goma"
  },
  {
    "level": 3,
    "code": 30926,
    "name": "Taíde"
  },
  {
    "level": 3,
    "code": 30927,
    "name": "Travassos"
  },
  {
    "level": 3,
    "code": 30929,
    "name": "Vilela"
  },
  {
    "level": 3,
    "code": 30930,
    "name": "União das freguesias de Águas Santas e Moure"
  },
  {
    "level": 3,
    "code": 30931,
    "name": "União das freguesias de Calvos e Frades"
  },
  {
    "level": 3,
    "code": 30932,
    "name": "União das freguesias de Campos e Louredo"
  },
  {
    "level": 3,
    "code": 30933,
    "name": "União das freguesias de Esperança e Brunhais"
  },
  {
    "level": 3,
    "code": 30934,
    "name": "União das freguesias de Fonte Arcada e Oliveira"
  },
  {
    "level": 3,
    "code": 30935,
    "name": "União das freguesias de Verim, Friande e Ajude"
  },
  {
    "level": 2,
    "code": 310,
    "name": "Terras de Bouro"
  },
  {
    "level": 3,
    "code": 31001,
    "name": "Balança"
  },
  {
    "level": 3,
    "code": 31003,
    "name": "Campo do Gerês"
  },
  {
    "level": 3,
    "code": 31004,
    "name": "Carvalheira"
  },
  {
    "level": 3,
    "code": 31008,
    "name": "Covide"
  },
  {
    "level": 3,
    "code": 31009,
    "name": "Gondoriz"
  },
  {
    "level": 3,
    "code": 31010,
    "name": "Moimenta"
  },
  {
    "level": 3,
    "code": 31012,
    "name": "Ribeira"
  },
  {
    "level": 3,
    "code": 31013,
    "name": "Rio Caldo"
  },
  {
    "level": 3,
    "code": 31014,
    "name": "Souto"
  },
  {
    "level": 3,
    "code": 31015,
    "name": "Valdosende"
  },
  {
    "level": 3,
    "code": 31017,
    "name": "Vilar da Veiga"
  },
  {
    "level": 3,
    "code": 31018,
    "name": "União das freguesias de Chamoim e Vilar"
  },
  {
    "level": 3,
    "code": 31019,
    "name": "União das freguesias de Chorense e Monte"
  },
  {
    "level": 3,
    "code": 31020,
    "name": "União das freguesias de Cibões e Brufe"
  },
  {
    "level": 2,
    "code": 311,
    "name": "Vieira do Minho"
  },
  {
    "level": 3,
    "code": 31105,
    "name": "Cantelães"
  },
  {
    "level": 3,
    "code": 31107,
    "name": "Eira Vedra"
  },
  {
    "level": 3,
    "code": 31108,
    "name": "Guilhofrei"
  },
  {
    "level": 3,
    "code": 31109,
    "name": "Louredo"
  },
  {
    "level": 3,
    "code": 31110,
    "name": "Mosteiro"
  },
  {
    "level": 3,
    "code": 31111,
    "name": "Parada do Bouro"
  },
  {
    "level": 3,
    "code": 31112,
    "name": "Pinheiro"
  },
  {
    "level": 3,
    "code": 31113,
    "name": "Rossas"
  },
  {
    "level": 3,
    "code": 31115,
    "name": "Salamonde"
  },
  {
    "level": 3,
    "code": 31118,
    "name": "Tabuaças"
  },
  {
    "level": 3,
    "code": 31120,
    "name": "Vieira do Minho"
  },
  {
    "level": 3,
    "code": 31122,
    "name": "União das freguesias de Anissó e Soutelo"
  },
  {
    "level": 3,
    "code": 31123,
    "name": "União das freguesias de Anjos e Vilar do Chão"
  },
  {
    "level": 3,
    "code": 31124,
    "name": "União das freguesias de Caniçada e Soengas"
  },
  {
    "level": 3,
    "code": 31125,
    "name": "União das freguesias de Ruivães e Campos"
  },
  {
    "level": 3,
    "code": 31126,
    "name": "União das freguesias de Ventosa e Cova"
  },
  {
    "level": 2,
    "code": 312,
    "name": "Vila Nova de Famalicão"
  },
  {
    "level": 3,
    "code": 31204,
    "name": "Bairro"
  },
  {
    "level": 3,
    "code": 31206,
    "name": "Brufe"
  },
  {
    "level": 3,
    "code": 31210,
    "name": "Castelões"
  },
  {
    "level": 3,
    "code": 31212,
    "name": "Cruz"
  },
  {
    "level": 3,
    "code": 31213,
    "name": "Delães"
  },
  {
    "level": 3,
    "code": 31215,
    "name": "Fradelos"
  },
  {
    "level": 3,
    "code": 31216,
    "name": "Gavião"
  },
  {
    "level": 3,
    "code": 31219,
    "name": "Joane"
  },
  {
    "level": 3,
    "code": 31221,
    "name": "Landim"
  },
  {
    "level": 3,
    "code": 31223,
    "name": "Louro"
  },
  {
    "level": 3,
    "code": 31224,
    "name": "Lousado"
  },
  {
    "level": 3,
    "code": 31225,
    "name": "Mogege"
  },
  {
    "level": 3,
    "code": 31227,
    "name": "Nine"
  },
  {
    "level": 3,
    "code": 31230,
    "name": "Pedome"
  },
  {
    "level": 3,
    "code": 31232,
    "name": "Pousada de Saramagos"
  },
  {
    "level": 3,
    "code": 31233,
    "name": "Requião"
  },
  {
    "level": 3,
    "code": 31234,
    "name": "Riba de Ave"
  },
  {
    "level": 3,
    "code": 31235,
    "name": "Ribeirão"
  },
  {
    "level": 3,
    "code": 31239,
    "name": "Oliveira (Santa Maria)"
  },
  {
    "level": 3,
    "code": 31241,
    "name": "Vale (São Martinho)"
  },
  {
    "level": 3,
    "code": 31242,
    "name": "Oliveira (São Mateus)"
  },
  {
    "level": 3,
    "code": 31247,
    "name": "Vermoim"
  },
  {
    "level": 3,
    "code": 31249,
    "name": "Vilarinho das Cambas"
  },
  {
    "level": 3,
    "code": 31250,
    "name": "União das freguesias de Antas e Abade de Vermoim"
  },
  {
    "level": 3,
    "code": 31251,
    "name": "União das freguesias de Arnoso (Santa Maria e Santa Eulália) e Sezures"
  },
  {
    "level": 3,
    "code": 31252,
    "name": "União das freguesias de Avidos e Lagoa"
  },
  {
    "level": 3,
    "code": 31253,
    "name": "União das freguesias de Carreira e Bente"
  },
  {
    "level": 3,
    "code": 31254,
    "name": "União das freguesias de Esmeriz e Cabeçudos"
  },
  {
    "level": 3,
    "code": 31255,
    "name": "União das freguesias de Gondifelos, Cavalões e Outiz"
  },
  {
    "level": 3,
    "code": 31256,
    "name": "União das freguesias de Lemenhe, Mouquim e Jesufrei"
  },
  {
    "level": 3,
    "code": 31257,
    "name": "União das freguesias de Ruivães e Novais"
  },
  {
    "level": 3,
    "code": 31258,
    "name": "União das freguesias de Seide"
  },
  {
    "level": 3,
    "code": 31259,
    "name": "União das freguesias de Vale (São Cosme), Telhado e Portela"
  },
  {
    "level": 3,
    "code": 31260,
    "name": "União das freguesias de Vila Nova de Famalicão e Calendário"
  },
  {
    "level": 2,
    "code": 313,
    "name": "Vila Verde"
  },
  {
    "level": 3,
    "code": 31304,
    "name": "Atiães"
  },
  {
    "level": 3,
    "code": 31308,
    "name": "Cabanelas"
  },
  {
    "level": 3,
    "code": 31309,
    "name": "Cervães"
  },
  {
    "level": 3,
    "code": 31311,
    "name": "Coucieiro"
  },
  {
    "level": 3,
    "code": 31313,
    "name": "Dossãos"
  },
  {
    "level": 3,
    "code": 31316,
    "name": "Freiriz"
  },
  {
    "level": 3,
    "code": 31317,
    "name": "Gême"
  },
  {
    "level": 3,
    "code": 31323,
    "name": "Lage"
  },
  {
    "level": 3,
    "code": 31324,
    "name": "Lanhas"
  },
  {
    "level": 3,
    "code": 31325,
    "name": "Loureira"
  },
  {
    "level": 3,
    "code": 31328,
    "name": "Moure"
  },
  {
    "level": 3,
    "code": 31330,
    "name": "Oleiros"
  },
  {
    "level": 3,
    "code": 31331,
    "name": "Parada de Gatim"
  },
  {
    "level": 3,
    "code": 31335,
    "name": "Pico"
  },
  {
    "level": 3,
    "code": 31337,
    "name": "Ponte"
  },
  {
    "level": 3,
    "code": 31340,
    "name": "Sabariz"
  },
  {
    "level": 3,
    "code": 31342,
    "name": "Vila de Prado"
  },
  {
    "level": 3,
    "code": 31350,
    "name": "Prado (São Miguel)"
  },
  {
    "level": 3,
    "code": 31352,
    "name": "Soutelo"
  },
  {
    "level": 3,
    "code": 31354,
    "name": "Turiz"
  },
  {
    "level": 3,
    "code": 31355,
    "name": "Valdreu"
  },
  {
    "level": 3,
    "code": 31359,
    "name": "Aboim da Nóbrega e Gondomar"
  },
  {
    "level": 3,
    "code": 31360,
    "name": "União das freguesias da Ribeira do Neiva"
  },
  {
    "level": 3,
    "code": 31361,
    "name": "União das freguesias de Carreiras (São Miguel) e Carreiras (Santiago)"
  },
  {
    "level": 3,
    "code": 31362,
    "name": "União das freguesias de Escariz (São Mamede) e Escariz (São Martinho)"
  },
  {
    "level": 3,
    "code": 31363,
    "name": "União das freguesias de Esqueiros, Nevogilde e Travassós"
  },
  {
    "level": 3,
    "code": 31364,
    "name": "União das freguesias de Marrancos e Arcozelo"
  },
  {
    "level": 3,
    "code": 31365,
    "name": "União das freguesias de Oriz (Santa Marinha) e Oriz (São Miguel)"
  },
  {
    "level": 3,
    "code": 31366,
    "name": "União das freguesias de Pico de Regalados, Gondiães e Mós"
  },
  {
    "level": 3,
    "code": 31367,
    "name": "União das freguesias de Sande, Vilarinho, Barros e Gomide"
  },
  {
    "level": 3,
    "code": 31368,
    "name": "União das freguesias de Valbom (São Pedro), Passô e Valbom (São Martinho)"
  },
  {
    "level": 3,
    "code": 31369,
    "name": "União das freguesias do Vade"
  },
  {
    "level": 3,
    "code": 31370,
    "name": "Vila Verde e Barbudo"
  },
  {
    "level": 2,
    "code": 314,
    "name": "Vizela"
  },
  {
    "level": 3,
    "code": 31401,
    "name": "Santa Eulália"
  },
  {
    "level": 3,
    "code": 31404,
    "name": "Infias"
  },
  {
    "level": 3,
    "code": 31406,
    "name": "Vizela (Santo Adrião)"
  },
  {
    "level": 3,
    "code": 31408,
    "name": "União das freguesias de Caldas de Vizela (São Miguel e São João)"
  },
  {
    "level": 3,
    "code": 31409,
    "name": "União das freguesias de Tagilde e Vizela (São Paio)"
  },
  {
    "level": 1,
    "code": 4,
    "name": "Bragança"
  },
  {
    "level": 2,
    "code": 401,
    "name": "Alfândega da Fé"
  },
  {
    "level": 3,
    "code": 40102,
    "name": "Alfândega da Fé"
  },
  {
    "level": 3,
    "code": 40103,
    "name": "Cerejais"
  },
  {
    "level": 3,
    "code": 40111,
    "name": "Sambade"
  },
  {
    "level": 3,
    "code": 40118,
    "name": "Vilar Chão"
  },
  {
    "level": 3,
    "code": 40119,
    "name": "Vilarelhos"
  },
  {
    "level": 3,
    "code": 40120,
    "name": "Vilares de Vilariça"
  },
  {
    "level": 3,
    "code": 40121,
    "name": "União das freguesias de Agrobom, Saldonha e Vale Pereiro"
  },
  {
    "level": 3,
    "code": 40122,
    "name": "União das freguesias de Eucisia, Gouveia e Valverde"
  },
  {
    "level": 3,
    "code": 40123,
    "name": "União das freguesias de Ferradosa e Sendim da Serra"
  },
  {
    "level": 3,
    "code": 40124,
    "name": "União das freguesias de Gebelim e Soeima"
  },
  {
    "level": 3,
    "code": 40125,
    "name": "União das freguesias de Parada e Sendim da Ribeira"
  },
  {
    "level": 3,
    "code": 40126,
    "name": "União das freguesias de Pombal e Vales"
  },
  {
    "level": 2,
    "code": 402,
    "name": "Bragança"
  },
  {
    "level": 3,
    "code": 40201,
    "name": "Alfaião"
  },
  {
    "level": 3,
    "code": 40203,
    "name": "Babe"
  },
  {
    "level": 3,
    "code": 40204,
    "name": "Baçal"
  },
  {
    "level": 3,
    "code": 40206,
    "name": "Carragosa"
  },
  {
    "level": 3,
    "code": 40209,
    "name": "Castro de Avelãs"
  },
  {
    "level": 3,
    "code": 40210,
    "name": "Coelhoso"
  },
  {
    "level": 3,
    "code": 40212,
    "name": "Donai"
  },
  {
    "level": 3,
    "code": 40213,
    "name": "Espinhosela"
  },
  {
    "level": 3,
    "code": 40215,
    "name": "França"
  },
  {
    "level": 3,
    "code": 40216,
    "name": "Gimonde"
  },
  {
    "level": 3,
    "code": 40217,
    "name": "Gondesende"
  },
  {
    "level": 3,
    "code": 40218,
    "name": "Gostei"
  },
  {
    "level": 3,
    "code": 40219,
    "name": "Grijó de Parada"
  },
  {
    "level": 3,
    "code": 40221,
    "name": "Macedo do Mato"
  },
  {
    "level": 3,
    "code": 40224,
    "name": "Mós"
  },
  {
    "level": 3,
    "code": 40225,
    "name": "Nogueira"
  },
  {
    "level": 3,
    "code": 40226,
    "name": "Outeiro"
  },
  {
    "level": 3,
    "code": 40229,
    "name": "Parâmio"
  },
  {
    "level": 3,
    "code": 40230,
    "name": "Pinela"
  },
  {
    "level": 3,
    "code": 40232,
    "name": "Quintanilha"
  },
  {
    "level": 3,
    "code": 40233,
    "name": "Quintela de Lampaças"
  },
  {
    "level": 3,
    "code": 40234,
    "name": "Rabal"
  },
  {
    "level": 3,
    "code": 40236,
    "name": "Rebordãos"
  },
  {
    "level": 3,
    "code": 40239,
    "name": "Salsas"
  },
  {
    "level": 3,
    "code": 40240,
    "name": "Samil"
  },
  {
    "level": 3,
    "code": 40241,
    "name": "Santa Comba de Rossas"
  },
  {
    "level": 3,
    "code": 40244,
    "name": "São Pedro de Sarracenos"
  },
  {
    "level": 3,
    "code": 40246,
    "name": "Sendas"
  },
  {
    "level": 3,
    "code": 40247,
    "name": "Serapicos"
  },
  {
    "level": 3,
    "code": 40248,
    "name": "Sortes"
  },
  {
    "level": 3,
    "code": 40249,
    "name": "Zoio"
  },
  {
    "level": 3,
    "code": 40250,
    "name": "União das freguesias de Aveleda e Rio de Onor"
  },
  {
    "level": 3,
    "code": 40251,
    "name": "União das freguesias de Castrelos e Carrazedo"
  },
  {
    "level": 3,
    "code": 40252,
    "name": "União das freguesias de Izeda, Calvelhe e Paradinha Nova"
  },
  {
    "level": 3,
    "code": 40253,
    "name": "União das freguesias de Parada e Faílde"
  },
  {
    "level": 3,
    "code": 40254,
    "name": "União das freguesias de Rebordainhos e Pombares"
  },
  {
    "level": 3,
    "code": 40255,
    "name": "União das freguesias de Rio Frio e Milhão"
  },
  {
    "level": 3,
    "code": 40256,
    "name": "União das freguesias de São Julião de Palácios e Deilão"
  },
  {
    "level": 3,
    "code": 40257,
    "name": "União das freguesias de Sé, Santa Maria e Meixedo"
  },
  {
    "level": 2,
    "code": 403,
    "name": "Carrazeda de Ansiães"
  },
  {
    "level": 3,
    "code": 40304,
    "name": "Carrazeda de Ansiães"
  },
  {
    "level": 3,
    "code": 40306,
    "name": "Fonte Longa"
  },
  {
    "level": 3,
    "code": 40308,
    "name": "Linhares"
  },
  {
    "level": 3,
    "code": 40309,
    "name": "Marzagão"
  },
  {
    "level": 3,
    "code": 40311,
    "name": "Parambos"
  },
  {
    "level": 3,
    "code": 40312,
    "name": "Pereiros"
  },
  {
    "level": 3,
    "code": 40313,
    "name": "Pinhal do Norte"
  },
  {
    "level": 3,
    "code": 40314,
    "name": "Pombal"
  },
  {
    "level": 3,
    "code": 40316,
    "name": "Seixo de Ansiães"
  },
  {
    "level": 3,
    "code": 40318,
    "name": "Vilarinho da Castanheira"
  },
  {
    "level": 3,
    "code": 40320,
    "name": "União das freguesias de Amedo e Zedes"
  },
  {
    "level": 3,
    "code": 40321,
    "name": "União das freguesias de Belver e Mogo de Malta"
  },
  {
    "level": 3,
    "code": 40322,
    "name": "União das freguesias de Castanheiro do Norte e Ribalonga"
  },
  {
    "level": 3,
    "code": 40323,
    "name": "União das freguesias de Lavandeira, Beira Grande e Selores"
  },
  {
    "level": 2,
    "code": 404,
    "name": "Freixo de Espada à Cinta"
  },
  {
    "level": 3,
    "code": 40404,
    "name": "Ligares"
  },
  {
    "level": 3,
    "code": 40406,
    "name": "Poiares"
  },
  {
    "level": 3,
    "code": 40407,
    "name": "União das freguesias de Freixo de Espada à Cinta e Mazouco"
  },
  {
    "level": 3,
    "code": 40408,
    "name": "União das freguesias de Lagoaça e Fornos"
  },
  {
    "level": 2,
    "code": 405,
    "name": "Macedo de Cavaleiros"
  },
  {
    "level": 3,
    "code": 40502,
    "name": "Amendoeira"
  },
  {
    "level": 3,
    "code": 40503,
    "name": "Arcas"
  },
  {
    "level": 3,
    "code": 40507,
    "name": "Carrapatas"
  },
  {
    "level": 3,
    "code": 40509,
    "name": "Chacim"
  },
  {
    "level": 3,
    "code": 40510,
    "name": "Cortiços"
  },
  {
    "level": 3,
    "code": 40511,
    "name": "Corujas"
  },
  {
    "level": 3,
    "code": 40514,
    "name": "Ferreira"
  },
  {
    "level": 3,
    "code": 40515,
    "name": "Grijó"
  },
  {
    "level": 3,
    "code": 40516,
    "name": "Lagoa"
  },
  {
    "level": 3,
    "code": 40517,
    "name": "Lamalonga"
  },
  {
    "level": 3,
    "code": 40518,
    "name": "Lamas"
  },
  {
    "level": 3,
    "code": 40519,
    "name": "Lombo"
  },
  {
    "level": 3,
    "code": 40520,
    "name": "Macedo de Cavaleiros"
  },
  {
    "level": 3,
    "code": 40521,
    "name": "Morais"
  },
  {
    "level": 3,
    "code": 40523,
    "name": "Olmos"
  },
  {
    "level": 3,
    "code": 40524,
    "name": "Peredo"
  },
  {
    "level": 3,
    "code": 40526,
    "name": "Salselas"
  },
  {
    "level": 3,
    "code": 40528,
    "name": "Sezulfe"
  },
  {
    "level": 3,
    "code": 40530,
    "name": "Talhas"
  },
  {
    "level": 3,
    "code": 40532,
    "name": "Vale Benfeito"
  },
  {
    "level": 3,
    "code": 40533,
    "name": "Vale da Porca"
  },
  {
    "level": 3,
    "code": 40534,
    "name": "Vale de Prados"
  },
  {
    "level": 3,
    "code": 40536,
    "name": "Vilarinho de Agrochão"
  },
  {
    "level": 3,
    "code": 40538,
    "name": "Vinhas"
  },
  {
    "level": 3,
    "code": 40539,
    "name": "União das freguesias de Ala e Vilarinho do Monte"
  },
  {
    "level": 3,
    "code": 40540,
    "name": "União das freguesias de Bornes e Burga"
  },
  {
    "level": 3,
    "code": 40541,
    "name": "União das freguesias de Castelãos e Vilar do Monte"
  },
  {
    "level": 3,
    "code": 40542,
    "name": "União das freguesias de Espadanedo, Edroso, Murçós e Soutelo Mourisco"
  },
  {
    "level": 3,
    "code": 40543,
    "name": "União das freguesias de Podence e Santa Combinha"
  },
  {
    "level": 3,
    "code": 40544,
    "name": "União das freguesias de Talhinhas e Bagueixe"
  },
  {
    "level": 2,
    "code": 406,
    "name": "Miranda do Douro"
  },
  {
    "level": 3,
    "code": 40604,
    "name": "Duas Igrejas"
  },
  {
    "level": 3,
    "code": 40605,
    "name": "Genísio"
  },
  {
    "level": 3,
    "code": 40607,
    "name": "Malhadas"
  },
  {
    "level": 3,
    "code": 40608,
    "name": "Miranda do Douro"
  },
  {
    "level": 3,
    "code": 40609,
    "name": "Palaçoulo"
  },
  {
    "level": 3,
    "code": 40611,
    "name": "Picote"
  },
  {
    "level": 3,
    "code": 40612,
    "name": "Póvoa"
  },
  {
    "level": 3,
    "code": 40613,
    "name": "São Martinho de Angueira"
  },
  {
    "level": 3,
    "code": 40616,
    "name": "Vila Chã de Braciosa"
  },
  {
    "level": 3,
    "code": 40618,
    "name": "União das freguesias de Constantim e Cicouro"
  },
  {
    "level": 3,
    "code": 40619,
    "name": "União das freguesias de Ifanes e Paradela"
  },
  {
    "level": 3,
    "code": 40620,
    "name": "União das freguesias de Sendim e Atenor"
  },
  {
    "level": 3,
    "code": 40621,
    "name": "União das freguesias de Silva e Águas Vivas"
  },
  {
    "level": 2,
    "code": 407,
    "name": "Mirandela"
  },
  {
    "level": 3,
    "code": 40701,
    "name": "Abambres"
  },
  {
    "level": 3,
    "code": 40702,
    "name": "Abreiro"
  },
  {
    "level": 3,
    "code": 40703,
    "name": "Aguieiras"
  },
  {
    "level": 3,
    "code": 40704,
    "name": "Alvites"
  },
  {
    "level": 3,
    "code": 40708,
    "name": "Bouça"
  },
  {
    "level": 3,
    "code": 40709,
    "name": "Cabanelas"
  },
  {
    "level": 3,
    "code": 40710,
    "name": "Caravelas"
  },
  {
    "level": 3,
    "code": 40711,
    "name": "Carvalhais"
  },
  {
    "level": 3,
    "code": 40712,
    "name": "Cedães"
  },
  {
    "level": 3,
    "code": 40713,
    "name": "Cobro"
  },
  {
    "level": 3,
    "code": 40714,
    "name": "Fradizela"
  },
  {
    "level": 3,
    "code": 40716,
    "name": "Frechas"
  },
  {
    "level": 3,
    "code": 40718,
    "name": "Lamas de Orelhão"
  },
  {
    "level": 3,
    "code": 40720,
    "name": "Mascarenhas"
  },
  {
    "level": 3,
    "code": 40721,
    "name": "Mirandela"
  },
  {
    "level": 3,
    "code": 40722,
    "name": "Múrias"
  },
  {
    "level": 3,
    "code": 40724,
    "name": "Passos"
  },
  {
    "level": 3,
    "code": 40727,
    "name": "São Pedro Velho"
  },
  {
    "level": 3,
    "code": 40728,
    "name": "São Salvador"
  },
  {
    "level": 3,
    "code": 40729,
    "name": "Suçães"
  },
  {
    "level": 3,
    "code": 40730,
    "name": "Torre de Dona Chama"
  },
  {
    "level": 3,
    "code": 40731,
    "name": "Vale de Asnes"
  },
  {
    "level": 3,
    "code": 40732,
    "name": "Vale de Gouvinhas"
  },
  {
    "level": 3,
    "code": 40733,
    "name": "Vale de Salgueiro"
  },
  {
    "level": 3,
    "code": 40734,
    "name": "Vale de Telhas"
  },
  {
    "level": 3,
    "code": 40738,
    "name": "União das freguesias de Avantos e Romeu"
  },
  {
    "level": 3,
    "code": 40739,
    "name": "União das Freguesias de Avidagos, Navalho e Pereira"
  },
  {
    "level": 3,
    "code": 40740,
    "name": "União das freguesias de Barcel, Marmelos e Valverde da Gestosa"
  },
  {
    "level": 3,
    "code": 40741,
    "name": "União das freguesias de Franco e Vila Boa"
  },
  {
    "level": 3,
    "code": 40742,
    "name": "União das freguesias de Freixeda e Vila Verde"
  },
  {
    "level": 2,
    "code": 408,
    "name": "Mogadouro"
  },
  {
    "level": 3,
    "code": 40801,
    "name": "Azinhoso"
  },
  {
    "level": 3,
    "code": 40802,
    "name": "Bemposta"
  },
  {
    "level": 3,
    "code": 40803,
    "name": "Bruçó"
  },
  {
    "level": 3,
    "code": 40804,
    "name": "Brunhoso"
  },
  {
    "level": 3,
    "code": 40807,
    "name": "Castelo Branco"
  },
  {
    "level": 3,
    "code": 40808,
    "name": "Castro Vicente"
  },
  {
    "level": 3,
    "code": 40809,
    "name": "Meirinhos"
  },
  {
    "level": 3,
    "code": 40811,
    "name": "Paradela"
  },
  {
    "level": 3,
    "code": 40812,
    "name": "Penas Roias"
  },
  {
    "level": 3,
    "code": 40813,
    "name": "Peredo da Bemposta"
  },
  {
    "level": 3,
    "code": 40815,
    "name": "Saldanha"
  },
  {
    "level": 3,
    "code": 40817,
    "name": "São Martinho do Peso"
  },
  {
    "level": 3,
    "code": 40819,
    "name": "Tó"
  },
  {
    "level": 3,
    "code": 40820,
    "name": "Travanca"
  },
  {
    "level": 3,
    "code": 40821,
    "name": "Urrós"
  },
  {
    "level": 3,
    "code": 40822,
    "name": "Vale da Madre"
  },
  {
    "level": 3,
    "code": 40826,
    "name": "Vila de Ala"
  },
  {
    "level": 3,
    "code": 40829,
    "name": "União das freguesias de Brunhozinho, Castanheira e Sanhoane"
  },
  {
    "level": 3,
    "code": 40830,
    "name": "União das freguesias de Mogadouro, Valverde, Vale de Porco e Vilar de Rei"
  },
  {
    "level": 3,
    "code": 40831,
    "name": "União das freguesias de Remondes e Soutelo"
  },
  {
    "level": 3,
    "code": 40832,
    "name": "União das freguesias de Vilarinho dos Galegos e Ventozelo"
  },
  {
    "level": 2,
    "code": 409,
    "name": "Torre de Moncorvo"
  },
  {
    "level": 3,
    "code": 40901,
    "name": "Açoreira"
  },
  {
    "level": 3,
    "code": 40903,
    "name": "Cabeça Boa"
  },
  {
    "level": 3,
    "code": 40905,
    "name": "Carviçais"
  },
  {
    "level": 3,
    "code": 40906,
    "name": "Castedo"
  },
  {
    "level": 3,
    "code": 40909,
    "name": "Horta da Vilariça"
  },
  {
    "level": 3,
    "code": 40910,
    "name": "Larinho"
  },
  {
    "level": 3,
    "code": 40911,
    "name": "Lousa"
  },
  {
    "level": 3,
    "code": 40913,
    "name": "Mós"
  },
  {
    "level": 3,
    "code": 40916,
    "name": "Torre de Moncorvo"
  },
  {
    "level": 3,
    "code": 40918,
    "name": "União das freguesias de Adeganha e Cardanha"
  },
  {
    "level": 3,
    "code": 40919,
    "name": "União das freguesias de Felgar e Souto da Velha"
  },
  {
    "level": 3,
    "code": 40920,
    "name": "União das freguesias de Felgueiras e Maçores"
  },
  {
    "level": 3,
    "code": 40921,
    "name": "União das freguesias de Urros e Peredo dos Castelhanos"
  },
  {
    "level": 2,
    "code": 410,
    "name": "Vila Flor"
  },
  {
    "level": 3,
    "code": 41002,
    "name": "Benlhevai"
  },
  {
    "level": 3,
    "code": 41005,
    "name": "Freixiel"
  },
  {
    "level": 3,
    "code": 41009,
    "name": "Roios"
  },
  {
    "level": 3,
    "code": 41010,
    "name": "Samões"
  },
  {
    "level": 3,
    "code": 41011,
    "name": "Sampaio"
  },
  {
    "level": 3,
    "code": 41012,
    "name": "Santa Comba de Vilariça"
  },
  {
    "level": 3,
    "code": 41013,
    "name": "Seixo de Manhoses"
  },
  {
    "level": 3,
    "code": 41014,
    "name": "Trindade"
  },
  {
    "level": 3,
    "code": 41015,
    "name": "Vale Frechoso"
  },
  {
    "level": 3,
    "code": 41020,
    "name": "União das freguesias de Assares e Lodões"
  },
  {
    "level": 3,
    "code": 41021,
    "name": "União das freguesias de Candoso e Carvalho de Egas"
  },
  {
    "level": 3,
    "code": 41022,
    "name": "União das freguesias de Valtorno e Mourão"
  },
  {
    "level": 3,
    "code": 41023,
    "name": "União das freguesias de Vila Flor e Nabo"
  },
  {
    "level": 3,
    "code": 41024,
    "name": "União das freguesias de Vilas Boas e Vilarinho das Azenhas"
  },
  {
    "level": 2,
    "code": 411,
    "name": "Vimioso"
  },
  {
    "level": 3,
    "code": 41103,
    "name": "Argozelo"
  },
  {
    "level": 3,
    "code": 41107,
    "name": "Carção"
  },
  {
    "level": 3,
    "code": 41108,
    "name": "Matela"
  },
  {
    "level": 3,
    "code": 41109,
    "name": "Pinelo"
  },
  {
    "level": 3,
    "code": 41110,
    "name": "Santulhão"
  },
  {
    "level": 3,
    "code": 41113,
    "name": "Vilar Seco"
  },
  {
    "level": 3,
    "code": 41114,
    "name": "Vimioso"
  },
  {
    "level": 3,
    "code": 41115,
    "name": "União das freguesias de Algoso, Campo de Víboras e Uva"
  },
  {
    "level": 3,
    "code": 41116,
    "name": "União das freguesias de Caçarelhos e Angueira"
  },
  {
    "level": 3,
    "code": 41117,
    "name": "União das freguesias de Vale de Frades e Avelanoso"
  },
  {
    "level": 2,
    "code": 412,
    "name": "Vinhais"
  },
  {
    "level": 3,
    "code": 41201,
    "name": "Agrochão"
  },
  {
    "level": 3,
    "code": 41203,
    "name": "Candedo"
  },
  {
    "level": 3,
    "code": 41204,
    "name": "Celas"
  },
  {
    "level": 3,
    "code": 41206,
    "name": "Edral"
  },
  {
    "level": 3,
    "code": 41207,
    "name": "Edrosa"
  },
  {
    "level": 3,
    "code": 41208,
    "name": "Ervedosa"
  },
  {
    "level": 3,
    "code": 41215,
    "name": "Paçó"
  },
  {
    "level": 3,
    "code": 41216,
    "name": "Penhas Juntas"
  },
  {
    "level": 3,
    "code": 41219,
    "name": "Rebordelo"
  },
  {
    "level": 3,
    "code": 41221,
    "name": "Santalha"
  },
  {
    "level": 3,
    "code": 41226,
    "name": "Tuizelo"
  },
  {
    "level": 3,
    "code": 41227,
    "name": "Vale das Fontes"
  },
  {
    "level": 3,
    "code": 41229,
    "name": "Vila Boa de Ousilhão"
  },
  {
    "level": 3,
    "code": 41230,
    "name": "Vila Verde"
  },
  {
    "level": 3,
    "code": 41232,
    "name": "Vilar de Ossos"
  },
  {
    "level": 3,
    "code": 41233,
    "name": "Vilar de Peregrinos"
  },
  {
    "level": 3,
    "code": 41234,
    "name": "Vilar Seco de Lomba"
  },
  {
    "level": 3,
    "code": 41235,
    "name": "Vinhais"
  },
  {
    "level": 3,
    "code": 41236,
    "name": "União das freguesias de Curopos e Vale de Janeiro"
  },
  {
    "level": 3,
    "code": 41237,
    "name": "União das freguesias de Moimenta e Montouto"
  },
  {
    "level": 3,
    "code": 41238,
    "name": "União das freguesias de Nunes e Ousilhão"
  },
  {
    "level": 3,
    "code": 41239,
    "name": "União das freguesias de Quirás e Pinheiro Novo"
  },
  {
    "level": 3,
    "code": 41240,
    "name": "União das freguesias de Sobreiro de Baixo e Alvaredos"
  },
  {
    "level": 3,
    "code": 41241,
    "name": "União das freguesias de Soeira, Fresulfe e Mofreita"
  },
  {
    "level": 3,
    "code": 41242,
    "name": "União das freguesias de Travanca e Santa Cruz"
  },
  {
    "level": 3,
    "code": 41243,
    "name": "União das freguesias de Vilar de Lomba e São Jomil"
  },
  {
    "level": 1,
    "code": 5,
    "name": "Castelo Branco"
  },
  {
    "level": 2,
    "code": 501,
    "name": "Belmonte"
  },
  {
    "level": 3,
    "code": 50102,
    "name": "Caria"
  },
  {
    "level": 3,
    "code": 50104,
    "name": "Inguias"
  },
  {
    "level": 3,
    "code": 50105,
    "name": "Maçainhas"
  },
  {
    "level": 3,
    "code": 50106,
    "name": "União das freguesias de Belmonte e Colmeal da Torre"
  },
  {
    "level": 2,
    "code": 502,
    "name": "Castelo Branco"
  },
  {
    "level": 3,
    "code": 50201,
    "name": "Alcains"
  },
  {
    "level": 3,
    "code": 50202,
    "name": "Almaceda"
  },
  {
    "level": 3,
    "code": 50203,
    "name": "Benquerenças"
  },
  {
    "level": 3,
    "code": 50205,
    "name": "Castelo Branco"
  },
  {
    "level": 3,
    "code": 50211,
    "name": "Lardosa"
  },
  {
    "level": 3,
    "code": 50212,
    "name": "Louriçal do Campo"
  },
  {
    "level": 3,
    "code": 50214,
    "name": "Malpica do Tejo"
  },
  {
    "level": 3,
    "code": 50216,
    "name": "Monforte da Beira"
  },
  {
    "level": 3,
    "code": 50220,
    "name": "Salgueiro do Campo"
  },
  {
    "level": 3,
    "code": 50221,
    "name": "Santo André das Tojeiras"
  },
  {
    "level": 3,
    "code": 50222,
    "name": "São Vicente da Beira"
  },
  {
    "level": 3,
    "code": 50223,
    "name": "Sarzedas"
  },
  {
    "level": 3,
    "code": 50225,
    "name": "Tinalhas"
  },
  {
    "level": 3,
    "code": 50226,
    "name": "União das freguesias de Cebolais de Cima e Retaxo"
  },
  {
    "level": 3,
    "code": 50227,
    "name": "União das freguesias de Escalos de Baixo e Mata"
  },
  {
    "level": 3,
    "code": 50228,
    "name": "União das freguesias de Escalos de Cima e Lousa"
  },
  {
    "level": 3,
    "code": 50229,
    "name": "União das freguesias de Freixial e Juncal do Campo"
  },
  {
    "level": 3,
    "code": 50230,
    "name": "União das freguesias de Ninho do Açor e Sobral do Campo"
  },
  {
    "level": 3,
    "code": 50231,
    "name": "União das freguesias de Póvoa de Rio de Moinhos e Cafede"
  },
  {
    "level": 2,
    "code": 503,
    "name": "Covilhã"
  },
  {
    "level": 3,
    "code": 50302,
    "name": "Aldeia de São Francisco de Assis"
  },
  {
    "level": 3,
    "code": 50305,
    "name": "Boidobra"
  },
  {
    "level": 3,
    "code": 50308,
    "name": "Cortes do Meio"
  },
  {
    "level": 3,
    "code": 50309,
    "name": "Dominguizo"
  },
  {
    "level": 3,
    "code": 50310,
    "name": "Erada"
  },
  {
    "level": 3,
    "code": 50311,
    "name": "Ferro"
  },
  {
    "level": 3,
    "code": 50312,
    "name": "Orjais"
  },
  {
    "level": 3,
    "code": 50314,
    "name": "Paul"
  },
  {
    "level": 3,
    "code": 50315,
    "name": "Peraboa"
  },
  {
    "level": 3,
    "code": 50318,
    "name": "São Jorge da Beira"
  },
  {
    "level": 3,
    "code": 50322,
    "name": "Sobral de São Miguel"
  },
  {
    "level": 3,
    "code": 50324,
    "name": "Tortosendo"
  },
  {
    "level": 3,
    "code": 50325,
    "name": "Unhais da Serra"
  },
  {
    "level": 3,
    "code": 50327,
    "name": "Verdelhos"
  },
  {
    "level": 3,
    "code": 50332,
    "name": "União das freguesias de Barco e Coutada"
  },
  {
    "level": 3,
    "code": 50333,
    "name": "União das freguesias de Cantar-Galo e Vila do Carvalho"
  },
  {
    "level": 3,
    "code": 50334,
    "name": "União das freguesias de Casegas e Ourondo"
  },
  {
    "level": 3,
    "code": 50335,
    "name": "União das freguesias de Covilhã e Canhoso"
  },
  {
    "level": 3,
    "code": 50336,
    "name": "União das freguesias de Peso e Vales do Rio"
  },
  {
    "level": 3,
    "code": 50337,
    "name": "União das freguesias de Teixoso e Sarzedo"
  },
  {
    "level": 3,
    "code": 50338,
    "name": "União das freguesias de Vale Formoso e Aldeia do Souto"
  },
  {
    "level": 2,
    "code": 504,
    "name": "Fundão"
  },
  {
    "level": 3,
    "code": 50401,
    "name": "Alcaide"
  },
  {
    "level": 3,
    "code": 50402,
    "name": "Alcaria"
  },
  {
    "level": 3,
    "code": 50403,
    "name": "Alcongosta"
  },
  {
    "level": 3,
    "code": 50406,
    "name": "Alpedrinha"
  },
  {
    "level": 3,
    "code": 50408,
    "name": "Barroca"
  },
  {
    "level": 3,
    "code": 50410,
    "name": "Bogas de Cima"
  },
  {
    "level": 3,
    "code": 50411,
    "name": "Capinha"
  },
  {
    "level": 3,
    "code": 50412,
    "name": "Castelejo"
  },
  {
    "level": 3,
    "code": 50413,
    "name": "Castelo Novo"
  },
  {
    "level": 3,
    "code": 50416,
    "name": "Fatela"
  },
  {
    "level": 3,
    "code": 50419,
    "name": "Lavacolhos"
  },
  {
    "level": 3,
    "code": 50420,
    "name": "Orca"
  },
  {
    "level": 3,
    "code": 50421,
    "name": "Pêro Viseu"
  },
  {
    "level": 3,
    "code": 50424,
    "name": "Silvares"
  },
  {
    "level": 3,
    "code": 50425,
    "name": "Soalheira"
  },
  {
    "level": 3,
    "code": 50426,
    "name": "Souto da Casa"
  },
  {
    "level": 3,
    "code": 50427,
    "name": "Telhado"
  },
  {
    "level": 3,
    "code": 50431,
    "name": "Enxames"
  },
  {
    "level": 3,
    "code": 50432,
    "name": "Três Povos"
  },
  {
    "level": 3,
    "code": 50433,
    "name": "União das freguesias de Janeiro de Cima e Bogas de Baixo"
  },
  {
    "level": 3,
    "code": 50434,
    "name": "União das freguesias de Fundão, Valverde, Donas, Aldeia de Joanes e Aldeia Nova do Cabo"
  },
  {
    "level": 3,
    "code": 50435,
    "name": "União das freguesias de Póvoa de Atalaia e Atalaia do Campo"
  },
  {
    "level": 3,
    "code": 50436,
    "name": "União das freguesias de Vale de Prazeres e Mata da Rainha"
  },
  {
    "level": 2,
    "code": 505,
    "name": "Idanha-a-Nova"
  },
  {
    "level": 3,
    "code": 50502,
    "name": "Aldeia de Santa Margarida"
  },
  {
    "level": 3,
    "code": 50505,
    "name": "Ladoeiro"
  },
  {
    "level": 3,
    "code": 50506,
    "name": "Medelim"
  },
  {
    "level": 3,
    "code": 50509,
    "name": "Oledo"
  },
  {
    "level": 3,
    "code": 50510,
    "name": "Penha Garcia"
  },
  {
    "level": 3,
    "code": 50511,
    "name": "Proença-a-Velha"
  },
  {
    "level": 3,
    "code": 50512,
    "name": "Rosmaninhal"
  },
  {
    "level": 3,
    "code": 50514,
    "name": "São Miguel de Acha"
  },
  {
    "level": 3,
    "code": 50516,
    "name": "Toulões"
  },
  {
    "level": 3,
    "code": 50518,
    "name": "União das freguesias de Idanha-a-Nova e Alcafozes"
  },
  {
    "level": 3,
    "code": 50519,
    "name": "União das freguesias de Monfortinho e Salvaterra do Extremo"
  },
  {
    "level": 3,
    "code": 50520,
    "name": "União das freguesias de Monsanto e Idanha-a-Velha"
  },
  {
    "level": 3,
    "code": 50521,
    "name": "União das freguesias de Zebreira e Segura"
  },
  {
    "level": 2,
    "code": 506,
    "name": "Oleiros"
  },
  {
    "level": 3,
    "code": 50601,
    "name": "Álvaro"
  },
  {
    "level": 3,
    "code": 50603,
    "name": "Cambas"
  },
  {
    "level": 3,
    "code": 50605,
    "name": "Isna"
  },
  {
    "level": 3,
    "code": 50606,
    "name": "Madeirã"
  },
  {
    "level": 3,
    "code": 50607,
    "name": "Mosteiro"
  },
  {
    "level": 3,
    "code": 50609,
    "name": "Orvalho"
  },
  {
    "level": 3,
    "code": 50610,
    "name": "Sarnadas de São Simão"
  },
  {
    "level": 3,
    "code": 50611,
    "name": "Sobral"
  },
  {
    "level": 3,
    "code": 50613,
    "name": "Estreito-Vilar Barroco"
  },
  {
    "level": 3,
    "code": 50614,
    "name": "Oleiros-Amieira"
  },
  {
    "level": 2,
    "code": 507,
    "name": "Penamacor"
  },
  {
    "level": 3,
    "code": 50704,
    "name": "Aranhas"
  },
  {
    "level": 3,
    "code": 50706,
    "name": "Benquerença"
  },
  {
    "level": 3,
    "code": 50707,
    "name": "Meimão"
  },
  {
    "level": 3,
    "code": 50708,
    "name": "Meimoa"
  },
  {
    "level": 3,
    "code": 50710,
    "name": "Penamacor"
  },
  {
    "level": 3,
    "code": 50711,
    "name": "Salvador"
  },
  {
    "level": 3,
    "code": 50712,
    "name": "Vale da Senhora da Póvoa"
  },
  {
    "level": 3,
    "code": 50713,
    "name": "União das freguesias de Aldeia do Bispo, Águas e Aldeia de João Pires"
  },
  {
    "level": 3,
    "code": 50714,
    "name": "União das freguesias de Pedrógão de São Pedro e Bemposta"
  },
  {
    "level": 2,
    "code": 508,
    "name": "Proença-a-Nova"
  },
  {
    "level": 3,
    "code": 50802,
    "name": "Montes da Senhora"
  },
  {
    "level": 3,
    "code": 50805,
    "name": "São Pedro do Esteval"
  },
  {
    "level": 3,
    "code": 50807,
    "name": "União das freguesias de Proença-a-Nova e Peral"
  },
  {
    "level": 3,
    "code": 50808,
    "name": "União das freguesias de Sobreira Formosa e Alvito da Beira"
  },
  {
    "level": 2,
    "code": 509,
    "name": "Sertã"
  },
  {
    "level": 3,
    "code": 50901,
    "name": "Cabeçudo"
  },
  {
    "level": 3,
    "code": 50902,
    "name": "Carvalhal"
  },
  {
    "level": 3,
    "code": 50903,
    "name": "Castelo"
  },
  {
    "level": 3,
    "code": 50911,
    "name": "Pedrógão Pequeno"
  },
  {
    "level": 3,
    "code": 50912,
    "name": "Sertã"
  },
  {
    "level": 3,
    "code": 50913,
    "name": "Troviscal"
  },
  {
    "level": 3,
    "code": 50914,
    "name": "Várzea dos Cavaleiros"
  },
  {
    "level": 3,
    "code": 50915,
    "name": "União das freguesias de Cernache do Bonjardim, Nesperal e Palhais"
  },
  {
    "level": 3,
    "code": 50916,
    "name": "União das freguesias de Cumeada e Marmeleiro"
  },
  {
    "level": 3,
    "code": 50917,
    "name": "União das freguesias de Ermida e Figueiredo"
  },
  {
    "level": 2,
    "code": 510,
    "name": "Vila de Rei"
  },
  {
    "level": 3,
    "code": 51001,
    "name": "Fundada"
  },
  {
    "level": 3,
    "code": 51002,
    "name": "São João do Peso"
  },
  {
    "level": 3,
    "code": 51003,
    "name": "Vila de Rei"
  },
  {
    "level": 2,
    "code": 511,
    "name": "Vila Velha de Ródão"
  },
  {
    "level": 3,
    "code": 51101,
    "name": "Fratel"
  },
  {
    "level": 3,
    "code": 51102,
    "name": "Perais"
  },
  {
    "level": 3,
    "code": 51103,
    "name": "Sarnadas de Ródão"
  },
  {
    "level": 3,
    "code": 51104,
    "name": "Vila Velha de Ródão"
  },
  {
    "level": 1,
    "code": 6,
    "name": "Coimbra"
  },
  {
    "level": 2,
    "code": 601,
    "name": "Arganil"
  },
  {
    "level": 3,
    "code": 60102,
    "name": "Arganil"
  },
  {
    "level": 3,
    "code": 60104,
    "name": "Benfeita"
  },
  {
    "level": 3,
    "code": 60105,
    "name": "Celavisa"
  },
  {
    "level": 3,
    "code": 60109,
    "name": "Folques"
  },
  {
    "level": 3,
    "code": 60111,
    "name": "Piódão"
  },
  {
    "level": 3,
    "code": 60112,
    "name": "Pomares"
  },
  {
    "level": 3,
    "code": 60113,
    "name": "Pombeiro da Beira"
  },
  {
    "level": 3,
    "code": 60114,
    "name": "São Martinho da Cortiça"
  },
  {
    "level": 3,
    "code": 60115,
    "name": "Sarzedo"
  },
  {
    "level": 3,
    "code": 60116,
    "name": "Secarias"
  },
  {
    "level": 3,
    "code": 60119,
    "name": "União das freguesias de Cepos e Teixeira"
  },
  {
    "level": 3,
    "code": 60120,
    "name": "União das freguesias de Cerdeira e Moura da Serra"
  },
  {
    "level": 3,
    "code": 60121,
    "name": "União das freguesias de Côja e Barril de Alva"
  },
  {
    "level": 3,
    "code": 60122,
    "name": "União das freguesias de Vila Cova de Alva e Anseriz"
  },
  {
    "level": 2,
    "code": 602,
    "name": "Cantanhede"
  },
  {
    "level": 3,
    "code": 60201,
    "name": "Ançã"
  },
  {
    "level": 3,
    "code": 60203,
    "name": "Cadima"
  },
  {
    "level": 3,
    "code": 60205,
    "name": "Cordinhã"
  },
  {
    "level": 3,
    "code": 60207,
    "name": "Febres"
  },
  {
    "level": 3,
    "code": 60208,
    "name": "Murtede"
  },
  {
    "level": 3,
    "code": 60209,
    "name": "Ourentã"
  },
  {
    "level": 3,
    "code": 60214,
    "name": "Tocha"
  },
  {
    "level": 3,
    "code": 60215,
    "name": "São Caetano"
  },
  {
    "level": 3,
    "code": 60218,
    "name": "Sanguinheira"
  },
  {
    "level": 3,
    "code": 60220,
    "name": "União das freguesias de Cantanhede e Pocariça"
  },
  {
    "level": 3,
    "code": 60221,
    "name": "União das freguesias de Covões e Camarneira"
  },
  {
    "level": 3,
    "code": 60222,
    "name": "União das freguesias de Portunhos e Outil"
  },
  {
    "level": 3,
    "code": 60223,
    "name": "União das freguesias de Sepins e Bolho"
  },
  {
    "level": 3,
    "code": 60224,
    "name": "União das freguesias de Vilamar e Corticeiro de Cima"
  },
  {
    "level": 2,
    "code": 603,
    "name": "Coimbra"
  },
  {
    "level": 3,
    "code": 60301,
    "name": "Almalaguês"
  },
  {
    "level": 3,
    "code": 60309,
    "name": "Brasfemes"
  },
  {
    "level": 3,
    "code": 60311,
    "name": "Ceira"
  },
  {
    "level": 3,
    "code": 60312,
    "name": "Cernache"
  },
  {
    "level": 3,
    "code": 60318,
    "name": "Santo António dos Olivais"
  },
  {
    "level": 3,
    "code": 60320,
    "name": "São João do Campo"
  },
  {
    "level": 3,
    "code": 60324,
    "name": "São Silvestre"
  },
  {
    "level": 3,
    "code": 60329,
    "name": "Torres do Mondego"
  },
  {
    "level": 3,
    "code": 60332,
    "name": "União das freguesias de Antuzede e Vil de Matos"
  },
  {
    "level": 3,
    "code": 60333,
    "name": "União das freguesias de Assafarge e Antanhol"
  },
  {
    "level": 3,
    "code": 60334,
    "name": "União das freguesias de Coimbra (Sé Nova, Santa Cruz, Almedina e São Bartolomeu)"
  },
  {
    "level": 3,
    "code": 60335,
    "name": "União das freguesias de Eiras e São Paulo de Frades"
  },
  {
    "level": 3,
    "code": 60336,
    "name": "União das freguesias de Santa Clara e Castelo Viegas"
  },
  {
    "level": 3,
    "code": 60337,
    "name": "União das freguesias de São Martinho de Árvore e Lamarosa"
  },
  {
    "level": 3,
    "code": 60338,
    "name": "União das freguesias de São Martinho do Bispo e Ribeira de Frades"
  },
  {
    "level": 3,
    "code": 60339,
    "name": "União das freguesias de Souselas e Botão"
  },
  {
    "level": 3,
    "code": 60340,
    "name": "União das freguesias de Taveiro, Ameal e Arzila"
  },
  {
    "level": 3,
    "code": 60341,
    "name": "União das freguesias de Trouxemil e Torre de Vilela"
  },
  {
    "level": 2,
    "code": 604,
    "name": "Condeixa-a-Nova"
  },
  {
    "level": 3,
    "code": 60401,
    "name": "Anobra"
  },
  {
    "level": 3,
    "code": 60406,
    "name": "Ega"
  },
  {
    "level": 3,
    "code": 60407,
    "name": "Furadouro"
  },
  {
    "level": 3,
    "code": 60410,
    "name": "Zambujal"
  },
  {
    "level": 3,
    "code": 60411,
    "name": "União das freguesias de Condeixa-a-Velha e Condeixa-a-Nova"
  },
  {
    "level": 3,
    "code": 60412,
    "name": "União das freguesias de Sebal e Belide"
  },
  {
    "level": 3,
    "code": 60413,
    "name": "União das freguesias de Vila Seca e Bem da Fé"
  },
  {
    "level": 2,
    "code": 605,
    "name": "Figueira da Foz"
  },
  {
    "level": 3,
    "code": 60502,
    "name": "Alqueidão"
  },
  {
    "level": 3,
    "code": 60507,
    "name": "Maiorca"
  },
  {
    "level": 3,
    "code": 60508,
    "name": "Marinha das Ondas"
  },
  {
    "level": 3,
    "code": 60512,
    "name": "Tavarede"
  },
  {
    "level": 3,
    "code": 60513,
    "name": "Vila Verde"
  },
  {
    "level": 3,
    "code": 60514,
    "name": "São Pedro"
  },
  {
    "level": 3,
    "code": 60515,
    "name": "Bom Sucesso"
  },
  {
    "level": 3,
    "code": 60518,
    "name": "Moinhos da Gândara"
  },
  {
    "level": 3,
    "code": 60519,
    "name": "Alhadas"
  },
  {
    "level": 3,
    "code": 60520,
    "name": "Buarcos e São Julião"
  },
  {
    "level": 3,
    "code": 60521,
    "name": "Ferreira-a-Nova"
  },
  {
    "level": 3,
    "code": 60522,
    "name": "Lavos"
  },
  {
    "level": 3,
    "code": 60523,
    "name": "Paião"
  },
  {
    "level": 3,
    "code": 60524,
    "name": "Quiaios"
  },
  {
    "level": 2,
    "code": 606,
    "name": "Góis"
  },
  {
    "level": 3,
    "code": 60601,
    "name": "Alvares"
  },
  {
    "level": 3,
    "code": 60604,
    "name": "Góis"
  },
  {
    "level": 3,
    "code": 60605,
    "name": "Vila Nova do Ceira"
  },
  {
    "level": 3,
    "code": 60606,
    "name": "União das freguesias de Cadafaz e Colmeal"
  },
  {
    "level": 2,
    "code": 607,
    "name": "Lousã"
  },
  {
    "level": 3,
    "code": 60704,
    "name": "Serpins"
  },
  {
    "level": 3,
    "code": 60706,
    "name": "Gândaras"
  },
  {
    "level": 3,
    "code": 60707,
    "name": "União das freguesias de Foz de Arouce e Casal de Ermio"
  },
  {
    "level": 3,
    "code": 60708,
    "name": "União das freguesias de Lousã e Vilarinho"
  },
  {
    "level": 2,
    "code": 608,
    "name": "Mira"
  },
  {
    "level": 3,
    "code": 60801,
    "name": "Mira"
  },
  {
    "level": 3,
    "code": 60802,
    "name": "Seixo"
  },
  {
    "level": 3,
    "code": 60803,
    "name": "Carapelhos"
  },
  {
    "level": 3,
    "code": 60804,
    "name": "Praia de Mira"
  },
  {
    "level": 2,
    "code": 609,
    "name": "Miranda do Corvo"
  },
  {
    "level": 3,
    "code": 60901,
    "name": "Lamas"
  },
  {
    "level": 3,
    "code": 60902,
    "name": "Miranda do Corvo"
  },
  {
    "level": 3,
    "code": 60905,
    "name": "Vila Nova"
  },
  {
    "level": 3,
    "code": 60906,
    "name": "União das freguesias de Semide e Rio Vide"
  },
  {
    "level": 2,
    "code": 610,
    "name": "Montemor-o-Velho"
  },
  {
    "level": 3,
    "code": 61002,
    "name": "Arazede"
  },
  {
    "level": 3,
    "code": 61003,
    "name": "Carapinheira"
  },
  {
    "level": 3,
    "code": 61005,
    "name": "Liceia"
  },
  {
    "level": 3,
    "code": 61006,
    "name": "Meãs do Campo"
  },
  {
    "level": 3,
    "code": 61008,
    "name": "Pereira"
  },
  {
    "level": 3,
    "code": 61009,
    "name": "Santo Varão"
  },
  {
    "level": 3,
    "code": 61010,
    "name": "Seixo de Gatões"
  },
  {
    "level": 3,
    "code": 61011,
    "name": "Tentúgal"
  },
  {
    "level": 3,
    "code": 61014,
    "name": "Ereira"
  },
  {
    "level": 3,
    "code": 61015,
    "name": "União das freguesias de Abrunheira, Verride e Vila Nova da Barca"
  },
  {
    "level": 3,
    "code": 61016,
    "name": "União das freguesias de Montemor-o-Velho e Gatões"
  },
  {
    "level": 2,
    "code": 611,
    "name": "Oliveira do Hospital"
  },
  {
    "level": 3,
    "code": 61101,
    "name": "Aldeia das Dez"
  },
  {
    "level": 3,
    "code": 61102,
    "name": "Alvoco das Várzeas"
  },
  {
    "level": 3,
    "code": 61103,
    "name": "Avô"
  },
  {
    "level": 3,
    "code": 61104,
    "name": "Bobadela"
  },
  {
    "level": 3,
    "code": 61106,
    "name": "Lagares"
  },
  {
    "level": 3,
    "code": 61109,
    "name": "Lourosa"
  },
  {
    "level": 3,
    "code": 61110,
    "name": "Meruge"
  },
  {
    "level": 3,
    "code": 61111,
    "name": "Nogueira do Cravo"
  },
  {
    "level": 3,
    "code": 61115,
    "name": "São Gião"
  },
  {
    "level": 3,
    "code": 61118,
    "name": "Seixo da Beira"
  },
  {
    "level": 3,
    "code": 61119,
    "name": "Travanca de Lagos"
  },
  {
    "level": 3,
    "code": 61122,
    "name": "União das freguesias de Ervedal e Vila Franca da Beira"
  },
  {
    "level": 3,
    "code": 61123,
    "name": "União das freguesias de Lagos da Beira e Lajeosa"
  },
  {
    "level": 3,
    "code": 61124,
    "name": "União das freguesias de Oliveira do Hospital e São Paio de Gramaços"
  },
  {
    "level": 3,
    "code": 61125,
    "name": "União das freguesias de Penalva de Alva e São Sebastião da Feira"
  },
  {
    "level": 3,
    "code": 61126,
    "name": "União das freguesias de Santa Ovaia e Vila Pouca da Beira"
  },
  {
    "level": 2,
    "code": 612,
    "name": "Pampilhosa da Serra"
  },
  {
    "level": 3,
    "code": 61201,
    "name": "Cabril"
  },
  {
    "level": 3,
    "code": 61202,
    "name": "Dornelas do Zêzere"
  },
  {
    "level": 3,
    "code": 61204,
    "name": "Janeiro de Baixo"
  },
  {
    "level": 3,
    "code": 61206,
    "name": "Pampilhosa da Serra"
  },
  {
    "level": 3,
    "code": 61207,
    "name": "Pessegueiro"
  },
  {
    "level": 3,
    "code": 61209,
    "name": "Unhais-o-Velho"
  },
  {
    "level": 3,
    "code": 61211,
    "name": "Fajão-Vidual"
  },
  {
    "level": 3,
    "code": 61212,
    "name": "Portela do Fojo-Machio"
  },
  {
    "level": 2,
    "code": 613,
    "name": "Penacova"
  },
  {
    "level": 3,
    "code": 61301,
    "name": "Carvalho"
  },
  {
    "level": 3,
    "code": 61302,
    "name": "Figueira de Lorvão"
  },
  {
    "level": 3,
    "code": 61304,
    "name": "Lorvão"
  },
  {
    "level": 3,
    "code": 61307,
    "name": "Penacova"
  },
  {
    "level": 3,
    "code": 61310,
    "name": "Sazes do Lorvão"
  },
  {
    "level": 3,
    "code": 61312,
    "name": "União das freguesias de Friúmes e Paradela"
  },
  {
    "level": 3,
    "code": 61313,
    "name": "União das freguesias de Oliveira do Mondego e Travanca do Mondego"
  },
  {
    "level": 3,
    "code": 61314,
    "name": "União das freguesias de São Pedro de Alva e São Paio de Mondego"
  },
  {
    "level": 2,
    "code": 614,
    "name": "Penela"
  },
  {
    "level": 3,
    "code": 61401,
    "name": "Cumeeira"
  },
  {
    "level": 3,
    "code": 61402,
    "name": "Espinhal"
  },
  {
    "level": 3,
    "code": 61403,
    "name": "Podentes"
  },
  {
    "level": 3,
    "code": 61407,
    "name": "União das freguesias de São Miguel, Santa Eufémia e Rabaçal"
  },
  {
    "level": 2,
    "code": 615,
    "name": "Soure"
  },
  {
    "level": 3,
    "code": 61501,
    "name": "Alfarelos"
  },
  {
    "level": 3,
    "code": 61504,
    "name": "Figueiró do Campo"
  },
  {
    "level": 3,
    "code": 61506,
    "name": "Granja do Ulmeiro"
  },
  {
    "level": 3,
    "code": 61508,
    "name": "Samuel"
  },
  {
    "level": 3,
    "code": 61509,
    "name": "Soure"
  },
  {
    "level": 3,
    "code": 61510,
    "name": "Tapéus"
  },
  {
    "level": 3,
    "code": 61511,
    "name": "Vila Nova de Anços"
  },
  {
    "level": 3,
    "code": 61512,
    "name": "Vinha da Rainha"
  },
  {
    "level": 3,
    "code": 61513,
    "name": "União das freguesias de Degracias e Pombalinho"
  },
  {
    "level": 3,
    "code": 61514,
    "name": "União das freguesias de Gesteira e Brunhós"
  },
  {
    "level": 2,
    "code": 616,
    "name": "Tábua"
  },
  {
    "level": 3,
    "code": 61602,
    "name": "Candosa"
  },
  {
    "level": 3,
    "code": 61603,
    "name": "Carapinha"
  },
  {
    "level": 3,
    "code": 61608,
    "name": "Midões"
  },
  {
    "level": 3,
    "code": 61609,
    "name": "Mouronho"
  },
  {
    "level": 3,
    "code": 61611,
    "name": "Póvoa de Midões"
  },
  {
    "level": 3,
    "code": 61612,
    "name": "São João da Boa Vista"
  },
  {
    "level": 3,
    "code": 61614,
    "name": "Tábua"
  },
  {
    "level": 3,
    "code": 61616,
    "name": "União das freguesias de Ázere e Covelo"
  },
  {
    "level": 3,
    "code": 61617,
    "name": "União das freguesias de Covas e Vila Nova de Oliveirinha"
  },
  {
    "level": 3,
    "code": 61618,
    "name": "União das freguesias de Espariz e Sinde"
  },
  {
    "level": 3,
    "code": 61619,
    "name": "União das freguesias de Pinheiro de Coja e Meda de Mouros"
  },
  {
    "level": 2,
    "code": 617,
    "name": "Vila Nova de Poiares"
  },
  {
    "level": 3,
    "code": 61701,
    "name": "Arrifana"
  },
  {
    "level": 3,
    "code": 61702,
    "name": "Lavegadas"
  },
  {
    "level": 3,
    "code": 61703,
    "name": "Poiares (Santo André)"
  },
  {
    "level": 3,
    "code": 61704,
    "name": "São Miguel de Poiares"
  },
  {
    "level": 1,
    "code": 7,
    "name": "Évora"
  },
  {
    "level": 2,
    "code": 701,
    "name": "Alandroal"
  },
  {
    "level": 3,
    "code": 70103,
    "name": "Santiago Maior"
  },
  {
    "level": 3,
    "code": 70104,
    "name": "Capelins (Santo António)"
  },
  {
    "level": 3,
    "code": 70105,
    "name": "Terena (São Pedro)"
  },
  {
    "level": 3,
    "code": 70107,
    "name": "União das freguesias de Alandroal (Nossa Senhora da Conceição), São Brás dos Matos (Mina do Bugalho) e Juromenha (Nossa Senhora do Loreto)"
  },
  {
    "level": 2,
    "code": 702,
    "name": "Arraiolos"
  },
  {
    "level": 3,
    "code": 70201,
    "name": "Arraiolos"
  },
  {
    "level": 3,
    "code": 70202,
    "name": "Igrejinha"
  },
  {
    "level": 3,
    "code": 70206,
    "name": "Vimieiro"
  },
  {
    "level": 3,
    "code": 70208,
    "name": "União das freguesias de Gafanhoeira (São Pedro) e Sabugueiro"
  },
  {
    "level": 3,
    "code": 70209,
    "name": "União das freguesias de São Gregório e Santa Justa"
  },
  {
    "level": 2,
    "code": 703,
    "name": "Borba"
  },
  {
    "level": 3,
    "code": 70301,
    "name": "Borba (Matriz)"
  },
  {
    "level": 3,
    "code": 70302,
    "name": "Orada"
  },
  {
    "level": 3,
    "code": 70303,
    "name": "Rio de Moinhos"
  },
  {
    "level": 3,
    "code": 70304,
    "name": "Borba (São Bartolomeu)"
  },
  {
    "level": 2,
    "code": 704,
    "name": "Estremoz"
  },
  {
    "level": 3,
    "code": 70401,
    "name": "Arcos"
  },
  {
    "level": 3,
    "code": 70402,
    "name": "Glória"
  },
  {
    "level": 3,
    "code": 70404,
    "name": "Évora Monte (Santa Maria)"
  },
  {
    "level": 3,
    "code": 70411,
    "name": "São Domingos de Ana Loura"
  },
  {
    "level": 3,
    "code": 70413,
    "name": "Veiros"
  },
  {
    "level": 3,
    "code": 70414,
    "name": "União das freguesias de Estremoz (Santa Maria e Santo André)"
  },
  {
    "level": 3,
    "code": 70415,
    "name": "União das freguesias de São Bento do Cortiço e Santo Estêvão"
  },
  {
    "level": 3,
    "code": 70416,
    "name": "União das freguesias de São Lourenço de Mamporcão e São Bento de Ana Loura"
  },
  {
    "level": 3,
    "code": 70417,
    "name": "União das freguesias do Ameixial (Santa Vitória e São Bento)"
  },
  {
    "level": 2,
    "code": 705,
    "name": "Évora"
  },
  {
    "level": 3,
    "code": 70502,
    "name": "Nossa Senhora da Graça do Divor"
  },
  {
    "level": 3,
    "code": 70503,
    "name": "Nossa Senhora de Machede"
  },
  {
    "level": 3,
    "code": 70506,
    "name": "São Bento do Mato"
  },
  {
    "level": 3,
    "code": 70509,
    "name": "São Miguel de Machede"
  },
  {
    "level": 3,
    "code": 70513,
    "name": "Torre de Coelheiros"
  },
  {
    "level": 3,
    "code": 70515,
    "name": "Canaviais"
  },
  {
    "level": 3,
    "code": 70522,
    "name": "União das freguesias de Bacelo e Senhora da Saúde"
  },
  {
    "level": 3,
    "code": 70523,
    "name": "União das freguesias de Évora (São Mamede, Sé, São Pedro e Santo Antão)"
  },
  {
    "level": 3,
    "code": 70524,
    "name": "União das freguesias de Malagueira e Horta das Figueiras"
  },
  {
    "level": 3,
    "code": 70525,
    "name": "União das freguesias de Nossa Senhora da Tourega e Nossa Senhora de Guadalupe"
  },
  {
    "level": 3,
    "code": 70526,
    "name": "União das freguesias de São Manços e São Vicente do Pigeiro"
  },
  {
    "level": 3,
    "code": 70527,
    "name": "União das freguesias de São Sebastião da Giesteira e Nossa Senhora da Boa Fé"
  },
  {
    "level": 2,
    "code": 706,
    "name": "Montemor-o-Novo"
  },
  {
    "level": 3,
    "code": 70601,
    "name": "Cabrela"
  },
  {
    "level": 3,
    "code": 70605,
    "name": "Santiago do Escoural"
  },
  {
    "level": 3,
    "code": 70606,
    "name": "São Cristóvão"
  },
  {
    "level": 3,
    "code": 70607,
    "name": "Ciborro"
  },
  {
    "level": 3,
    "code": 70610,
    "name": "Foros de Vale de Figueira"
  },
  {
    "level": 3,
    "code": 70611,
    "name": "União das freguesias de Cortiçadas de Lavre e Lavre"
  },
  {
    "level": 3,
    "code": 70612,
    "name": "União das freguesias de Nossa Senhora da Vila, Nossa Senhora do Bispo e Silveiras"
  },
  {
    "level": 2,
    "code": 707,
    "name": "Mora"
  },
  {
    "level": 3,
    "code": 70701,
    "name": "Brotas"
  },
  {
    "level": 3,
    "code": 70702,
    "name": "Cabeção"
  },
  {
    "level": 3,
    "code": 70703,
    "name": "Mora"
  },
  {
    "level": 3,
    "code": 70704,
    "name": "Pavia"
  },
  {
    "level": 2,
    "code": 708,
    "name": "Mourão"
  },
  {
    "level": 3,
    "code": 70801,
    "name": "Granja"
  },
  {
    "level": 3,
    "code": 70802,
    "name": "Luz"
  },
  {
    "level": 3,
    "code": 70803,
    "name": "Mourão"
  },
  {
    "level": 2,
    "code": 709,
    "name": "Portel"
  },
  {
    "level": 3,
    "code": 70903,
    "name": "Monte do Trigo"
  },
  {
    "level": 3,
    "code": 70905,
    "name": "Portel"
  },
  {
    "level": 3,
    "code": 70906,
    "name": "Santana"
  },
  {
    "level": 3,
    "code": 70908,
    "name": "Vera Cruz"
  },
  {
    "level": 3,
    "code": 70909,
    "name": "União das freguesias de Amieira e Alqueva"
  },
  {
    "level": 3,
    "code": 70910,
    "name": "União das freguesias de São Bartolomeu do Outeiro e Oriola"
  },
  {
    "level": 2,
    "code": 710,
    "name": "Redondo"
  },
  {
    "level": 3,
    "code": 71001,
    "name": "Montoito"
  },
  {
    "level": 3,
    "code": 71002,
    "name": "Redondo"
  },
  {
    "level": 2,
    "code": 711,
    "name": "Reguengos de Monsaraz"
  },
  {
    "level": 3,
    "code": 71102,
    "name": "Corval"
  },
  {
    "level": 3,
    "code": 71103,
    "name": "Monsaraz"
  },
  {
    "level": 3,
    "code": 71104,
    "name": "Reguengos de Monsaraz"
  },
  {
    "level": 3,
    "code": 71106,
    "name": "União das freguesias de Campo e Campinho"
  },
  {
    "level": 2,
    "code": 712,
    "name": "Vendas Novas"
  },
  {
    "level": 3,
    "code": 71201,
    "name": "Vendas Novas"
  },
  {
    "level": 3,
    "code": 71202,
    "name": "Landeira"
  },
  {
    "level": 2,
    "code": 713,
    "name": "Viana do Alentejo"
  },
  {
    "level": 3,
    "code": 71301,
    "name": "Alcáçovas"
  },
  {
    "level": 3,
    "code": 71302,
    "name": "Viana do Alentejo"
  },
  {
    "level": 3,
    "code": 71303,
    "name": "Aguiar"
  },
  {
    "level": 2,
    "code": 714,
    "name": "Vila Viçosa"
  },
  {
    "level": 3,
    "code": 71401,
    "name": "Bencatel"
  },
  {
    "level": 3,
    "code": 71402,
    "name": "Ciladas"
  },
  {
    "level": 3,
    "code": 71404,
    "name": "Pardais"
  },
  {
    "level": 3,
    "code": 71406,
    "name": "Nossa Senhora da Conceição e São Bartolomeu"
  },
  {
    "level": 1,
    "code": 8,
    "name": "Faro"
  },
  {
    "level": 2,
    "code": 801,
    "name": "Albufeira"
  },
  {
    "level": 3,
    "code": 80102,
    "name": "Guia"
  },
  {
    "level": 3,
    "code": 80103,
    "name": "Paderne"
  },
  {
    "level": 3,
    "code": 80104,
    "name": "Ferreiras"
  },
  {
    "level": 3,
    "code": 80106,
    "name": "Albufeira e Olhos de Água"
  },
  {
    "level": 2,
    "code": 802,
    "name": "Alcoutim"
  },
  {
    "level": 3,
    "code": 80202,
    "name": "Giões"
  },
  {
    "level": 3,
    "code": 80203,
    "name": "Martim Longo"
  },
  {
    "level": 3,
    "code": 80205,
    "name": "Vaqueiros"
  },
  {
    "level": 3,
    "code": 80206,
    "name": "União das freguesias de Alcoutim e Pereiro"
  },
  {
    "level": 2,
    "code": 803,
    "name": "Aljezur"
  },
  {
    "level": 3,
    "code": 80301,
    "name": "Aljezur"
  },
  {
    "level": 3,
    "code": 80302,
    "name": "Bordeira"
  },
  {
    "level": 3,
    "code": 80303,
    "name": "Odeceixe"
  },
  {
    "level": 3,
    "code": 80304,
    "name": "Rogil"
  },
  {
    "level": 2,
    "code": 804,
    "name": "Castro Marim"
  },
  {
    "level": 3,
    "code": 80401,
    "name": "Azinhal"
  },
  {
    "level": 3,
    "code": 80402,
    "name": "Castro Marim"
  },
  {
    "level": 3,
    "code": 80403,
    "name": "Odeleite"
  },
  {
    "level": 3,
    "code": 80404,
    "name": "Altura"
  },
  {
    "level": 2,
    "code": 805,
    "name": "Faro"
  },
  {
    "level": 3,
    "code": 80503,
    "name": "Santa Bárbara de Nexe"
  },
  {
    "level": 3,
    "code": 80506,
    "name": "Montenegro"
  },
  {
    "level": 3,
    "code": 80507,
    "name": "União das freguesias de Conceição e Estoi"
  },
  {
    "level": 3,
    "code": 80508,
    "name": "União das freguesias de Faro (Sé e São Pedro)"
  },
  {
    "level": 2,
    "code": 806,
    "name": "Lagoa"
  },
  {
    "level": 3,
    "code": 80602,
    "name": "Ferragudo"
  },
  {
    "level": 3,
    "code": 80604,
    "name": "Porches"
  },
  {
    "level": 3,
    "code": 80607,
    "name": "União das freguesias de Estômbar e Parchal"
  },
  {
    "level": 3,
    "code": 80608,
    "name": "União das freguesias de Lagoa e Carvoeiro"
  },
  {
    "level": 2,
    "code": 807,
    "name": "Lagos"
  },
  {
    "level": 3,
    "code": 80703,
    "name": "Luz"
  },
  {
    "level": 3,
    "code": 80704,
    "name": "Odiáxere"
  },
  {
    "level": 3,
    "code": 80707,
    "name": "União das freguesias de Bensafrim e Barão de São João"
  },
  {
    "level": 3,
    "code": 80708,
    "name": "São Gonçalo de Lagos"
  },
  {
    "level": 2,
    "code": 808,
    "name": "Loulé"
  },
  {
    "level": 3,
    "code": 80801,
    "name": "Almancil"
  },
  {
    "level": 3,
    "code": 80802,
    "name": "Alte"
  },
  {
    "level": 3,
    "code": 80803,
    "name": "Ameixial"
  },
  {
    "level": 3,
    "code": 80804,
    "name": "Boliqueime"
  },
  {
    "level": 3,
    "code": 80805,
    "name": "Quarteira"
  },
  {
    "level": 3,
    "code": 80807,
    "name": "Salir"
  },
  {
    "level": 3,
    "code": 80808,
    "name": "Loulé (São Clemente)"
  },
  {
    "level": 3,
    "code": 80809,
    "name": "Loulé (São Sebastião)"
  },
  {
    "level": 3,
    "code": 80812,
    "name": "União de freguesias de Querença, Tôr e Benafim"
  },
  {
    "level": 2,
    "code": 809,
    "name": "Monchique"
  },
  {
    "level": 3,
    "code": 80901,
    "name": "Alferce"
  },
  {
    "level": 3,
    "code": 80902,
    "name": "Marmelete"
  },
  {
    "level": 3,
    "code": 80903,
    "name": "Monchique"
  },
  {
    "level": 2,
    "code": 810,
    "name": "Olhão"
  },
  {
    "level": 3,
    "code": 81003,
    "name": "Olhão"
  },
  {
    "level": 3,
    "code": 81004,
    "name": "Pechão"
  },
  {
    "level": 3,
    "code": 81005,
    "name": "Quelfes"
  },
  {
    "level": 3,
    "code": 81006,
    "name": "União das freguesias de Moncarapacho e Fuseta"
  },
  {
    "level": 2,
    "code": 811,
    "name": "Portimão"
  },
  {
    "level": 3,
    "code": 81101,
    "name": "Alvor"
  },
  {
    "level": 3,
    "code": 81102,
    "name": "Mexilhoeira Grande"
  },
  {
    "level": 3,
    "code": 81103,
    "name": "Portimão"
  },
  {
    "level": 2,
    "code": 812,
    "name": "São Brás de Alportel"
  },
  {
    "level": 3,
    "code": 81201,
    "name": "São Brás de Alportel"
  },
  {
    "level": 2,
    "code": 813,
    "name": "Silves"
  },
  {
    "level": 3,
    "code": 81303,
    "name": "Armação de Pêra"
  },
  {
    "level": 3,
    "code": 81305,
    "name": "São Bartolomeu de Messines"
  },
  {
    "level": 3,
    "code": 81306,
    "name": "São Marcos da Serra"
  },
  {
    "level": 3,
    "code": 81307,
    "name": "Silves"
  },
  {
    "level": 3,
    "code": 81309,
    "name": "União das freguesias de Alcantarilha e Pêra"
  },
  {
    "level": 3,
    "code": 81310,
    "name": "União das freguesias de Algoz e Tunes"
  },
  {
    "level": 2,
    "code": 814,
    "name": "Tavira"
  },
  {
    "level": 3,
    "code": 81401,
    "name": "Cachopo"
  },
  {
    "level": 3,
    "code": 81404,
    "name": "Santa Catarina da Fonte do Bispo"
  },
  {
    "level": 3,
    "code": 81408,
    "name": "Santa Luzia"
  },
  {
    "level": 3,
    "code": 81410,
    "name": "União das freguesias de Conceição e Cabanas de Tavira"
  },
  {
    "level": 3,
    "code": 81411,
    "name": "União das freguesias de Luz de Tavira e Santo Estêvão"
  },
  {
    "level": 3,
    "code": 81412,
    "name": "União das freguesias de Tavira (Santa Maria e Santiago)"
  },
  {
    "level": 2,
    "code": 815,
    "name": "Vila do Bispo"
  },
  {
    "level": 3,
    "code": 81501,
    "name": "Barão de São Miguel"
  },
  {
    "level": 3,
    "code": 81502,
    "name": "Budens"
  },
  {
    "level": 3,
    "code": 81504,
    "name": "Sagres"
  },
  {
    "level": 3,
    "code": 81506,
    "name": "Vila do Bispo e Raposeira"
  },
  {
    "level": 2,
    "code": 816,
    "name": "Vila Real de Santo António"
  },
  {
    "level": 3,
    "code": 81601,
    "name": "Vila Nova de Cacela"
  },
  {
    "level": 3,
    "code": 81602,
    "name": "Vila Real de Santo António"
  },
  {
    "level": 3,
    "code": 81603,
    "name": "Monte Gordo"
  },
  {
    "level": 1,
    "code": 9,
    "name": "Guarda"
  },
  {
    "level": 2,
    "code": 901,
    "name": "Aguiar da Beira"
  },
  {
    "level": 3,
    "code": 90102,
    "name": "Carapito"
  },
  {
    "level": 3,
    "code": 90103,
    "name": "Cortiçada"
  },
  {
    "level": 3,
    "code": 90105,
    "name": "Dornelas"
  },
  {
    "level": 3,
    "code": 90106,
    "name": "Eirado"
  },
  {
    "level": 3,
    "code": 90107,
    "name": "Forninhos"
  },
  {
    "level": 3,
    "code": 90109,
    "name": "Pena Verde"
  },
  {
    "level": 3,
    "code": 90110,
    "name": "Pinheiro"
  },
  {
    "level": 3,
    "code": 90114,
    "name": "União das freguesias de Aguiar da Beira e Coruche"
  },
  {
    "level": 3,
    "code": 90115,
    "name": "União das freguesias de Sequeiros e Gradiz"
  },
  {
    "level": 3,
    "code": 90116,
    "name": "União das freguesias de Souto de Aguiar da Beira e Valverde"
  },
  {
    "level": 2,
    "code": 902,
    "name": "Almeida"
  },
  {
    "level": 3,
    "code": 90203,
    "name": "Almeida"
  },
  {
    "level": 3,
    "code": 90207,
    "name": "Castelo Bom"
  },
  {
    "level": 3,
    "code": 90209,
    "name": "Freineda"
  },
  {
    "level": 3,
    "code": 90210,
    "name": "Freixo"
  },
  {
    "level": 3,
    "code": 90213,
    "name": "Malhada Sorda"
  },
  {
    "level": 3,
    "code": 90219,
    "name": "Nave de Haver"
  },
  {
    "level": 3,
    "code": 90224,
    "name": "São Pedro de Rio Seco"
  },
  {
    "level": 3,
    "code": 90227,
    "name": "Vale da Mula"
  },
  {
    "level": 3,
    "code": 90229,
    "name": "Vilar Formoso"
  },
  {
    "level": 3,
    "code": 90230,
    "name": "União das freguesias de Amoreira, Parada e Cabreira"
  },
  {
    "level": 3,
    "code": 90231,
    "name": "União das freguesias de Azinhal, Peva e Valverde"
  },
  {
    "level": 3,
    "code": 90232,
    "name": "União das freguesias de Castelo Mendo, Ade, Monteperobolso e Mesquitela"
  },
  {
    "level": 3,
    "code": 90233,
    "name": "União das freguesias de Junça e Naves"
  },
  {
    "level": 3,
    "code": 90234,
    "name": "União das freguesias de Leomil, Mido, Senouras e Aldeia Nova"
  },
  {
    "level": 3,
    "code": 90235,
    "name": "União das freguesias de Malpartida e Vale de Coelha"
  },
  {
    "level": 3,
    "code": 90236,
    "name": "União das freguesias de Miuzela e Porto de Ovelha"
  },
  {
    "level": 2,
    "code": 903,
    "name": "Celorico da Beira"
  },
  {
    "level": 3,
    "code": 90302,
    "name": "Baraçal"
  },
  {
    "level": 3,
    "code": 90304,
    "name": "Carrapichana"
  },
  {
    "level": 3,
    "code": 90306,
    "name": "Forno Telheiro"
  },
  {
    "level": 3,
    "code": 90307,
    "name": "Lajeosa do Mondego"
  },
  {
    "level": 3,
    "code": 90308,
    "name": "Linhares"
  },
  {
    "level": 3,
    "code": 90309,
    "name": "Maçal do Chão"
  },
  {
    "level": 3,
    "code": 90310,
    "name": "Mesquitela"
  },
  {
    "level": 3,
    "code": 90311,
    "name": "Minhocal"
  },
  {
    "level": 3,
    "code": 90312,
    "name": "Prados"
  },
  {
    "level": 3,
    "code": 90314,
    "name": "Ratoeira"
  },
  {
    "level": 3,
    "code": 90318,
    "name": "Vale de Azares"
  },
  {
    "level": 3,
    "code": 90322,
    "name": "Casas do Soeiro"
  },
  {
    "level": 3,
    "code": 90323,
    "name": "União das freguesias de Açores e Velosa"
  },
  {
    "level": 3,
    "code": 90324,
    "name": "União das freguesias de Celorico (São Pedro e Santa Maria) e Vila Boa do Mondego"
  },
  {
    "level": 3,
    "code": 90325,
    "name": "União das freguesias de Cortiçô da Serra, Vide entre Vinhas e Salgueirais"
  },
  {
    "level": 3,
    "code": 90326,
    "name": "União das freguesias de Rapa e Cadafaz"
  },
  {
    "level": 2,
    "code": 904,
    "name": "Figueira de Castelo Rodrigo"
  },
  {
    "level": 3,
    "code": 90403,
    "name": "Castelo Rodrigo"
  },
  {
    "level": 3,
    "code": 90406,
    "name": "Escalhão"
  },
  {
    "level": 3,
    "code": 90408,
    "name": "Figueira de Castelo Rodrigo"
  },
  {
    "level": 3,
    "code": 90410,
    "name": "Mata de Lobos"
  },
  {
    "level": 3,
    "code": 90415,
    "name": "Vermiosa"
  },
  {
    "level": 3,
    "code": 90418,
    "name": "União das freguesias de Algodres, Vale de Afonsinho e Vilar de Amargo"
  },
  {
    "level": 3,
    "code": 90419,
    "name": "União das freguesias de Almofala e Escarigo"
  },
  {
    "level": 3,
    "code": 90420,
    "name": "União das freguesias de Cinco Vilas e Reigada"
  },
  {
    "level": 3,
    "code": 90421,
    "name": "União das freguesias de Freixeda do Torrão, Quintã de Pêro Martins e Penha de Águia"
  },
  {
    "level": 3,
    "code": 90422,
    "name": "União das freguesias do Colmeal e Vilar Torpim"
  },
  {
    "level": 2,
    "code": 905,
    "name": "Fornos de Algodres"
  },
  {
    "level": 3,
    "code": 90501,
    "name": "Algodres"
  },
  {
    "level": 3,
    "code": 90502,
    "name": "Casal Vasco"
  },
  {
    "level": 3,
    "code": 90504,
    "name": "Figueiró da Granja"
  },
  {
    "level": 3,
    "code": 90505,
    "name": "Fornos de Algodres"
  },
  {
    "level": 3,
    "code": 90507,
    "name": "Infias"
  },
  {
    "level": 3,
    "code": 90509,
    "name": "Maceira"
  },
  {
    "level": 3,
    "code": 90510,
    "name": "Matança"
  },
  {
    "level": 3,
    "code": 90511,
    "name": "Muxagata"
  },
  {
    "level": 3,
    "code": 90512,
    "name": "Queiriz"
  },
  {
    "level": 3,
    "code": 90517,
    "name": "União das freguesias de Cortiçô e Vila Chã"
  },
  {
    "level": 3,
    "code": 90518,
    "name": "União das freguesias de Juncais, Vila Ruiva e Vila Soeiro do Chão"
  },
  {
    "level": 3,
    "code": 90519,
    "name": "União das freguesias de Sobral Pichorro e Fuinhas"
  },
  {
    "level": 2,
    "code": 906,
    "name": "Gouveia"
  },
  {
    "level": 3,
    "code": 90602,
    "name": "Arcozelo"
  },
  {
    "level": 3,
    "code": 90603,
    "name": "Cativelos"
  },
  {
    "level": 3,
    "code": 90605,
    "name": "Folgosinho"
  },
  {
    "level": 3,
    "code": 90612,
    "name": "Nespereira"
  },
  {
    "level": 3,
    "code": 90613,
    "name": "Paços da Serra"
  },
  {
    "level": 3,
    "code": 90614,
    "name": "Ribamondego"
  },
  {
    "level": 3,
    "code": 90617,
    "name": "São Paio"
  },
  {
    "level": 3,
    "code": 90619,
    "name": "Vila Cortês da Serra"
  },
  {
    "level": 3,
    "code": 90620,
    "name": "Vila Franca da Serra"
  },
  {
    "level": 3,
    "code": 90621,
    "name": "Vila Nova de Tazem"
  },
  {
    "level": 3,
    "code": 90623,
    "name": "União das freguesias de Aldeias e Mangualde da Serra"
  },
  {
    "level": 3,
    "code": 90624,
    "name": "União das freguesias de Figueiró da Serra e Freixo da Serra"
  },
  {
    "level": 3,
    "code": 90625,
    "name": "Gouveia"
  },
  {
    "level": 3,
    "code": 90626,
    "name": "União das freguesias de Melo e Nabais"
  },
  {
    "level": 3,
    "code": 90627,
    "name": "União das freguesias de Moimenta da Serra e Vinhó"
  },
  {
    "level": 3,
    "code": 90628,
    "name": "União das freguesias de Rio Torto e Lagarinhos"
  },
  {
    "level": 2,
    "code": 907,
    "name": "Guarda"
  },
  {
    "level": 3,
    "code": 90703,
    "name": "Aldeia do Bispo"
  },
  {
    "level": 3,
    "code": 90704,
    "name": "Aldeia Viçosa"
  },
  {
    "level": 3,
    "code": 90705,
    "name": "Alvendre"
  },
  {
    "level": 3,
    "code": 90706,
    "name": "Arrifana"
  },
  {
    "level": 3,
    "code": 90708,
    "name": "Avelãs da Ribeira"
  },
  {
    "level": 3,
    "code": 90709,
    "name": "Benespera"
  },
  {
    "level": 3,
    "code": 90711,
    "name": "Casal de Cinza"
  },
  {
    "level": 3,
    "code": 90712,
    "name": "Castanheira"
  },
  {
    "level": 3,
    "code": 90713,
    "name": "Cavadoude"
  },
  {
    "level": 3,
    "code": 90714,
    "name": "Codesseiro"
  },
  {
    "level": 3,
    "code": 90716,
    "name": "Faia"
  },
  {
    "level": 3,
    "code": 90717,
    "name": "Famalicão"
  },
  {
    "level": 3,
    "code": 90718,
    "name": "Fernão Joanes"
  },
  {
    "level": 3,
    "code": 90721,
    "name": "Gonçalo Bocas"
  },
  {
    "level": 3,
    "code": 90722,
    "name": "João Antão"
  },
  {
    "level": 3,
    "code": 90723,
    "name": "Maçainhas"
  },
  {
    "level": 3,
    "code": 90724,
    "name": "Marmeleiro"
  },
  {
    "level": 3,
    "code": 90725,
    "name": "Meios"
  },
  {
    "level": 3,
    "code": 90728,
    "name": "Panoias de Cima"
  },
  {
    "level": 3,
    "code": 90729,
    "name": "Pega"
  },
  {
    "level": 3,
    "code": 90730,
    "name": "Pêra do Moço"
  },
  {
    "level": 3,
    "code": 90732,
    "name": "Porto da Carne"
  },
  {
    "level": 3,
    "code": 90734,
    "name": "Ramela"
  },
  {
    "level": 3,
    "code": 90738,
    "name": "Santana da Azinha"
  },
  {
    "level": 3,
    "code": 90744,
    "name": "Sobral da Serra"
  },
  {
    "level": 3,
    "code": 90746,
    "name": "Vale de Estrela"
  },
  {
    "level": 3,
    "code": 90747,
    "name": "Valhelhas"
  },
  {
    "level": 3,
    "code": 90748,
    "name": "Vela"
  },
  {
    "level": 3,
    "code": 90749,
    "name": "Videmonte"
  },
  {
    "level": 3,
    "code": 90750,
    "name": "Vila Cortês do Mondego"
  },
  {
    "level": 3,
    "code": 90751,
    "name": "Vila Fernando"
  },
  {
    "level": 3,
    "code": 90752,
    "name": "Vila Franca do Deão"
  },
  {
    "level": 3,
    "code": 90753,
    "name": "Vila Garcia"
  },
  {
    "level": 3,
    "code": 90757,
    "name": "Gonçalo"
  },
  {
    "level": 3,
    "code": 90758,
    "name": "Guarda"
  },
  {
    "level": 3,
    "code": 90759,
    "name": "Jarmelo São Miguel"
  },
  {
    "level": 3,
    "code": 90760,
    "name": "Jarmelo São Pedro"
  },
  {
    "level": 3,
    "code": 90761,
    "name": "União de freguesias de Avelãs de Ambom e Rocamondo"
  },
  {
    "level": 3,
    "code": 90762,
    "name": "União de freguesias de Corujeira e Trinta"
  },
  {
    "level": 3,
    "code": 90763,
    "name": "União de freguesias de Mizarela, Pêro Soares e Vila Soeiro"
  },
  {
    "level": 3,
    "code": 90764,
    "name": "União de freguesias de Pousade e Albardo"
  },
  {
    "level": 3,
    "code": 90765,
    "name": "União de freguesias de Rochoso e Monte Margarida"
  },
  {
    "level": 3,
    "code": 90766,
    "name": "Adão"
  },
  {
    "level": 2,
    "code": 908,
    "name": "Manteigas"
  },
  {
    "level": 3,
    "code": 90801,
    "name": "Sameiro"
  },
  {
    "level": 3,
    "code": 90802,
    "name": "Manteigas (Santa Maria)"
  },
  {
    "level": 3,
    "code": 90803,
    "name": "Manteigas (São Pedro)"
  },
  {
    "level": 3,
    "code": 90804,
    "name": "Vale de Amoreira"
  },
  {
    "level": 2,
    "code": 909,
    "name": "Mêda"
  },
  {
    "level": 3,
    "code": 90901,
    "name": "Aveloso"
  },
  {
    "level": 3,
    "code": 90902,
    "name": "Barreira"
  },
  {
    "level": 3,
    "code": 90905,
    "name": "Coriscada"
  },
  {
    "level": 3,
    "code": 90907,
    "name": "Longroiva"
  },
  {
    "level": 3,
    "code": 90908,
    "name": "Marialva"
  },
  {
    "level": 3,
    "code": 90912,
    "name": "Poço do Canto"
  },
  {
    "level": 3,
    "code": 90914,
    "name": "Rabaçal"
  },
  {
    "level": 3,
    "code": 90915,
    "name": "Ranhados"
  },
  {
    "level": 3,
    "code": 90917,
    "name": "Mêda, Outeiro de Gatos e Fonte Longa"
  },
  {
    "level": 3,
    "code": 90918,
    "name": "Prova e Casteição"
  },
  {
    "level": 3,
    "code": 90919,
    "name": "União das freguesias de Vale Flor, Carvalhal e Pai Penela"
  },
  {
    "level": 2,
    "code": 910,
    "name": "Pinhel"
  },
  {
    "level": 3,
    "code": 91009,
    "name": "Ervedosa"
  },
  {
    "level": 3,
    "code": 91010,
    "name": "Freixedas"
  },
  {
    "level": 3,
    "code": 91012,
    "name": "Lamegal"
  },
  {
    "level": 3,
    "code": 91013,
    "name": "Lameiras"
  },
  {
    "level": 3,
    "code": 91014,
    "name": "Manigoto"
  },
  {
    "level": 3,
    "code": 91015,
    "name": "Pala"
  },
  {
    "level": 3,
    "code": 91017,
    "name": "Pinhel"
  },
  {
    "level": 3,
    "code": 91018,
    "name": "Pínzio"
  },
  {
    "level": 3,
    "code": 91024,
    "name": "Souro Pires"
  },
  {
    "level": 3,
    "code": 91027,
    "name": "Vascoveiro"
  },
  {
    "level": 3,
    "code": 91028,
    "name": "Agregação das freguesias Sul de Pinhel"
  },
  {
    "level": 3,
    "code": 91032,
    "name": "Alto do Palurdo"
  },
  {
    "level": 3,
    "code": 91029,
    "name": "Alverca da Beira/Bouça Cova"
  },
  {
    "level": 3,
    "code": 91030,
    "name": "Terras de Massueime"
  },
  {
    "level": 3,
    "code": 91035,
    "name": "União das freguesias de Atalaia e Safurdão"
  },
  {
    "level": 3,
    "code": 91031,
    "name": "Valbom/Bogalhal"
  },
  {
    "level": 3,
    "code": 91033,
    "name": "Vale do Côa"
  },
  {
    "level": 3,
    "code": 91034,
    "name": "Vale do Massueime"
  },
  {
    "level": 2,
    "code": 911,
    "name": "Sabugal"
  },
  {
    "level": 3,
    "code": 91101,
    "name": "Águas Belas"
  },
  {
    "level": 3,
    "code": 91102,
    "name": "Aldeia do Bispo"
  },
  {
    "level": 3,
    "code": 91103,
    "name": "Aldeia da Ponte"
  },
  {
    "level": 3,
    "code": 91106,
    "name": "Aldeia Velha"
  },
  {
    "level": 3,
    "code": 91107,
    "name": "Alfaiates"
  },
  {
    "level": 3,
    "code": 91109,
    "name": "Baraçal"
  },
  {
    "level": 3,
    "code": 91110,
    "name": "Bendada"
  },
  {
    "level": 3,
    "code": 91111,
    "name": "Bismula"
  },
  {
    "level": 3,
    "code": 91112,
    "name": "Casteleiro"
  },
  {
    "level": 3,
    "code": 91113,
    "name": "Cerdeira"
  },
  {
    "level": 3,
    "code": 91114,
    "name": "Fóios"
  },
  {
    "level": 3,
    "code": 91118,
    "name": "Malcata"
  },
  {
    "level": 3,
    "code": 91120,
    "name": "Nave"
  },
  {
    "level": 3,
    "code": 91123,
    "name": "Quadrazais"
  },
  {
    "level": 3,
    "code": 91124,
    "name": "Quintas de São Bartolomeu"
  },
  {
    "level": 3,
    "code": 91125,
    "name": "Rapoula do Côa"
  },
  {
    "level": 3,
    "code": 91126,
    "name": "Rebolosa"
  },
  {
    "level": 3,
    "code": 91127,
    "name": "Rendo"
  },
  {
    "level": 3,
    "code": 91133,
    "name": "Sortelha"
  },
  {
    "level": 3,
    "code": 91134,
    "name": "Souto"
  },
  {
    "level": 3,
    "code": 91136,
    "name": "Vale de Espinho"
  },
  {
    "level": 3,
    "code": 91138,
    "name": "Vila Boa"
  },
  {
    "level": 3,
    "code": 91139,
    "name": "Vila do Touro"
  },
  {
    "level": 3,
    "code": 91141,
    "name": "União das freguesias de Aldeia da Ribeira, Vilar Maior e Badamalos"
  },
  {
    "level": 3,
    "code": 91142,
    "name": "União das freguesias de Lajeosa e Forcalhos"
  },
  {
    "level": 3,
    "code": 91143,
    "name": "União das freguesias de Pousafoles do Bispo, Pena Lobo e Lomba"
  },
  {
    "level": 3,
    "code": 91144,
    "name": "União das freguesias de Ruvina, Ruivós e Vale das Éguas"
  },
  {
    "level": 3,
    "code": 91145,
    "name": "União das freguesias do Sabugal e Aldeia de Santo António"
  },
  {
    "level": 3,
    "code": 91146,
    "name": "União das freguesias de Santo Estêvão e Moita"
  },
  {
    "level": 3,
    "code": 91147,
    "name": "União das freguesias de Seixo do Côa e Vale Longo"
  },
  {
    "level": 2,
    "code": 912,
    "name": "Seia"
  },
  {
    "level": 3,
    "code": 91201,
    "name": "Alvoco da Serra"
  },
  {
    "level": 3,
    "code": 91205,
    "name": "Girabolhos"
  },
  {
    "level": 3,
    "code": 91207,
    "name": "Loriga"
  },
  {
    "level": 3,
    "code": 91208,
    "name": "Paranhos"
  },
  {
    "level": 3,
    "code": 91209,
    "name": "Pinhanços"
  },
  {
    "level": 3,
    "code": 91210,
    "name": "Sabugueiro"
  },
  {
    "level": 3,
    "code": 91212,
    "name": "Sandomil"
  },
  {
    "level": 3,
    "code": 91213,
    "name": "Santa Comba"
  },
  {
    "level": 3,
    "code": 91216,
    "name": "Santiago"
  },
  {
    "level": 3,
    "code": 91219,
    "name": "Sazes da Beira"
  },
  {
    "level": 3,
    "code": 91221,
    "name": "Teixeira"
  },
  {
    "level": 3,
    "code": 91224,
    "name": "Travancinha"
  },
  {
    "level": 3,
    "code": 91225,
    "name": "Valezim"
  },
  {
    "level": 3,
    "code": 91228,
    "name": "Vila Cova à Coelheira"
  },
  {
    "level": 3,
    "code": 91230,
    "name": "União das freguesias de Carragozela e Várzea de Meruge"
  },
  {
    "level": 3,
    "code": 91231,
    "name": "União das freguesias de Sameice e Santa Eulália"
  },
  {
    "level": 3,
    "code": 91232,
    "name": "União das freguesias de Santa Marinha e São Martinho"
  },
  {
    "level": 3,
    "code": 91233,
    "name": "União das freguesias de Seia, São Romão e Lapa dos Dinheiros"
  },
  {
    "level": 3,
    "code": 91234,
    "name": "União das freguesias de Torrozelo e Folhadosa"
  },
  {
    "level": 3,
    "code": 91235,
    "name": "União das freguesias de Tourais e Lajes"
  },
  {
    "level": 3,
    "code": 91236,
    "name": "União das freguesias de Vide e Cabeça"
  },
  {
    "level": 2,
    "code": 913,
    "name": "Trancoso"
  },
  {
    "level": 3,
    "code": 91301,
    "name": "Aldeia Nova"
  },
  {
    "level": 3,
    "code": 91303,
    "name": "Castanheira"
  },
  {
    "level": 3,
    "code": 91304,
    "name": "Cogula"
  },
  {
    "level": 3,
    "code": 91305,
    "name": "Cótimos"
  },
  {
    "level": 3,
    "code": 91307,
    "name": "Fiães"
  },
  {
    "level": 3,
    "code": 91309,
    "name": "Granja"
  },
  {
    "level": 3,
    "code": 91310,
    "name": "Guilheiro"
  },
  {
    "level": 3,
    "code": 91311,
    "name": "Moimentinha"
  },
  {
    "level": 3,
    "code": 91312,
    "name": "Moreira de Rei"
  },
  {
    "level": 3,
    "code": 91313,
    "name": "Palhais"
  },
  {
    "level": 3,
    "code": 91314,
    "name": "Póvoa do Concelho"
  },
  {
    "level": 3,
    "code": 91315,
    "name": "Reboleiro"
  },
  {
    "level": 3,
    "code": 91316,
    "name": "Rio de Mel"
  },
  {
    "level": 3,
    "code": 91321,
    "name": "Tamanhos"
  },
  {
    "level": 3,
    "code": 91325,
    "name": "Valdujo"
  },
  {
    "level": 3,
    "code": 91330,
    "name": "União das freguesias de Freches e Torres"
  },
  {
    "level": 3,
    "code": 91331,
    "name": "União das freguesias de Torre do Terrenho, Sebadelhe da Serra e Terrenho"
  },
  {
    "level": 3,
    "code": 91332,
    "name": "União das freguesias de Trancoso (São Pedro e Santa Maria) e Souto Maior"
  },
  {
    "level": 3,
    "code": 91333,
    "name": "União das freguesias de Vale do Seixo e Vila Garcia"
  },
  {
    "level": 3,
    "code": 91334,
    "name": "União das freguesias de Vila Franca das Naves e Feital"
  },
  {
    "level": 3,
    "code": 91335,
    "name": "União das freguesias de Vilares e Carnicães"
  },
  {
    "level": 2,
    "code": 914,
    "name": "Vila Nova de Foz Côa"
  },
  {
    "level": 3,
    "code": 91401,
    "name": "Almendra"
  },
  {
    "level": 3,
    "code": 91402,
    "name": "Castelo Melhor"
  },
  {
    "level": 3,
    "code": 91403,
    "name": "Cedovim"
  },
  {
    "level": 3,
    "code": 91404,
    "name": "Chãs"
  },
  {
    "level": 3,
    "code": 91405,
    "name": "Custóias"
  },
  {
    "level": 3,
    "code": 91407,
    "name": "Horta"
  },
  {
    "level": 3,
    "code": 91410,
    "name": "Muxagata"
  },
  {
    "level": 3,
    "code": 91411,
    "name": "Numão"
  },
  {
    "level": 3,
    "code": 91412,
    "name": "Santa Comba"
  },
  {
    "level": 3,
    "code": 91414,
    "name": "Sebadelhe"
  },
  {
    "level": 3,
    "code": 91415,
    "name": "Seixas"
  },
  {
    "level": 3,
    "code": 91416,
    "name": "Touça"
  },
  {
    "level": 3,
    "code": 91418,
    "name": "Freixo de Numão"
  },
  {
    "level": 3,
    "code": 91419,
    "name": "Vila Nova de Foz Côa"
  },
  {
    "level": 1,
    "code": 10,
    "name": "Leiria"
  },
  {
    "level": 2,
    "code": 1001,
    "name": "Alcobaça"
  },
  {
    "level": 3,
    "code": 100102,
    "name": "Alfeizerão"
  },
  {
    "level": 3,
    "code": 100104,
    "name": "Bárrio"
  },
  {
    "level": 3,
    "code": 100105,
    "name": "Benedita"
  },
  {
    "level": 3,
    "code": 100106,
    "name": "Cela"
  },
  {
    "level": 3,
    "code": 100108,
    "name": "Évora de Alcobaça"
  },
  {
    "level": 3,
    "code": 100109,
    "name": "Maiorga"
  },
  {
    "level": 3,
    "code": 100112,
    "name": "São Martinho do Porto"
  },
  {
    "level": 3,
    "code": 100114,
    "name": "Turquel"
  },
  {
    "level": 3,
    "code": 100116,
    "name": "Vimeiro"
  },
  {
    "level": 3,
    "code": 100120,
    "name": "Aljubarrota"
  },
  {
    "level": 3,
    "code": 100121,
    "name": "União das freguesias de Alcobaça e Vestiaria"
  },
  {
    "level": 3,
    "code": 100122,
    "name": "União das freguesias de Coz, Alpedriz e Montes"
  },
  {
    "level": 3,
    "code": 100123,
    "name": "União das freguesias de Pataias e Martingança"
  },
  {
    "level": 2,
    "code": 1002,
    "name": "Alvaiázere"
  },
  {
    "level": 3,
    "code": 100201,
    "name": "Almoster"
  },
  {
    "level": 3,
    "code": 100204,
    "name": "Maçãs de Dona Maria"
  },
  {
    "level": 3,
    "code": 100205,
    "name": "Pelmá"
  },
  {
    "level": 3,
    "code": 100208,
    "name": "Alvaiázere"
  },
  {
    "level": 3,
    "code": 100209,
    "name": "Pussos São Pedro"
  },
  {
    "level": 2,
    "code": 1003,
    "name": "Ansião"
  },
  {
    "level": 3,
    "code": 100301,
    "name": "Alvorge"
  },
  {
    "level": 3,
    "code": 100303,
    "name": "Avelar"
  },
  {
    "level": 3,
    "code": 100304,
    "name": "Chão de Couce"
  },
  {
    "level": 3,
    "code": 100306,
    "name": "Pousaflores"
  },
  {
    "level": 3,
    "code": 100307,
    "name": "Santiago da Guarda"
  },
  {
    "level": 3,
    "code": 100309,
    "name": "Ansião"
  },
  {
    "level": 2,
    "code": 1004,
    "name": "Batalha"
  },
  {
    "level": 3,
    "code": 100401,
    "name": "Batalha"
  },
  {
    "level": 3,
    "code": 100402,
    "name": "Reguengo do Fetal"
  },
  {
    "level": 3,
    "code": 100403,
    "name": "São Mamede"
  },
  {
    "level": 3,
    "code": 100404,
    "name": "Golpilheira"
  },
  {
    "level": 2,
    "code": 1005,
    "name": "Bombarral"
  },
  {
    "level": 3,
    "code": 100502,
    "name": "Carvalhal"
  },
  {
    "level": 3,
    "code": 100503,
    "name": "Roliça"
  },
  {
    "level": 3,
    "code": 100505,
    "name": "Pó"
  },
  {
    "level": 3,
    "code": 100506,
    "name": "União das freguesias do Bombarral e Vale Covo"
  },
  {
    "level": 2,
    "code": 1006,
    "name": "Caldas da Rainha"
  },
  {
    "level": 3,
    "code": 100601,
    "name": "A dos Francos"
  },
  {
    "level": 3,
    "code": 100602,
    "name": "Alvorninha"
  },
  {
    "level": 3,
    "code": 100604,
    "name": "Carvalhal Benfeito"
  },
  {
    "level": 3,
    "code": 100606,
    "name": "Foz do Arelho"
  },
  {
    "level": 3,
    "code": 100607,
    "name": "Landal"
  },
  {
    "level": 3,
    "code": 100608,
    "name": "Nadadouro"
  },
  {
    "level": 3,
    "code": 100609,
    "name": "Salir de Matos"
  },
  {
    "level": 3,
    "code": 100611,
    "name": "Santa Catarina"
  },
  {
    "level": 3,
    "code": 100615,
    "name": "Vidais"
  },
  {
    "level": 3,
    "code": 100617,
    "name": "União das freguesias de Caldas da Rainha - Nossa Senhora do Pópulo, Coto e São Gregório"
  },
  {
    "level": 3,
    "code": 100618,
    "name": "União das freguesias de Caldas da Rainha - Santo Onofre e Serra do Bouro"
  },
  {
    "level": 3,
    "code": 100619,
    "name": "União das freguesias de Tornada e Salir do Porto"
  },
  {
    "level": 2,
    "code": 1007,
    "name": "Castanheira de Pêra"
  },
  {
    "level": 3,
    "code": 100703,
    "name": "União das freguesias de Castanheira de Pêra e Coentral"
  },
  {
    "level": 2,
    "code": 1008,
    "name": "Figueiró dos Vinhos"
  },
  {
    "level": 3,
    "code": 100801,
    "name": "Aguda"
  },
  {
    "level": 3,
    "code": 100802,
    "name": "Arega"
  },
  {
    "level": 3,
    "code": 100803,
    "name": "Campelo"
  },
  {
    "level": 3,
    "code": 100806,
    "name": "União das freguesias de Figueiró dos Vinhos e Bairradas"
  },
  {
    "level": 2,
    "code": 1009,
    "name": "Leiria"
  },
  {
    "level": 3,
    "code": 100901,
    "name": "Amor"
  },
  {
    "level": 3,
    "code": 100902,
    "name": "Arrabal"
  },
  {
    "level": 3,
    "code": 100907,
    "name": "Caranguejeira"
  },
  {
    "level": 3,
    "code": 100909,
    "name": "Coimbrão"
  },
  {
    "level": 3,
    "code": 100913,
    "name": "Maceira"
  },
  {
    "level": 3,
    "code": 100915,
    "name": "Milagres"
  },
  {
    "level": 3,
    "code": 100921,
    "name": "Regueira de Pontes"
  },
  {
    "level": 3,
    "code": 100925,
    "name": "Bajouca"
  },
  {
    "level": 3,
    "code": 100926,
    "name": "Bidoeira de Cima"
  },
  {
    "level": 3,
    "code": 100932,
    "name": "União das freguesias de Colmeias e Memória"
  },
  {
    "level": 3,
    "code": 100933,
    "name": "União das freguesias de Leiria, Pousos, Barreira e Cortes"
  },
  {
    "level": 3,
    "code": 100934,
    "name": "União das freguesias de Marrazes e Barosa"
  },
  {
    "level": 3,
    "code": 100935,
    "name": "União das freguesias de Monte Real e Carvide"
  },
  {
    "level": 3,
    "code": 100936,
    "name": "União das freguesias de Monte Redondo e Carreira"
  },
  {
    "level": 3,
    "code": 100937,
    "name": "União das freguesias de Parceiros e Azoia"
  },
  {
    "level": 3,
    "code": 100938,
    "name": "União das freguesias de Santa Catarina da Serra e Chainça"
  },
  {
    "level": 3,
    "code": 100939,
    "name": "União das freguesias de Santa Eufémia e Boa Vista"
  },
  {
    "level": 3,
    "code": 100940,
    "name": "União das freguesias de Souto da Carpalhosa e Ortigosa"
  },
  {
    "level": 2,
    "code": 1010,
    "name": "Marinha Grande"
  },
  {
    "level": 3,
    "code": 101001,
    "name": "Marinha Grande"
  },
  {
    "level": 3,
    "code": 101002,
    "name": "Vieira de Leiria"
  },
  {
    "level": 3,
    "code": 101003,
    "name": "Moita"
  },
  {
    "level": 2,
    "code": 1011,
    "name": "Nazaré"
  },
  {
    "level": 3,
    "code": 101101,
    "name": "Famalicão"
  },
  {
    "level": 3,
    "code": 101102,
    "name": "Nazaré"
  },
  {
    "level": 3,
    "code": 101103,
    "name": "Valado dos Frades"
  },
  {
    "level": 2,
    "code": 1012,
    "name": "Óbidos"
  },
  {
    "level": 3,
    "code": 101201,
    "name": "A dos Negros"
  },
  {
    "level": 3,
    "code": 101202,
    "name": "Amoreira"
  },
  {
    "level": 3,
    "code": 101203,
    "name": "Olho Marinho"
  },
  {
    "level": 3,
    "code": 101207,
    "name": "Vau"
  },
  {
    "level": 3,
    "code": 101208,
    "name": "Gaeiras"
  },
  {
    "level": 3,
    "code": 101209,
    "name": "Usseira"
  },
  {
    "level": 3,
    "code": 101210,
    "name": "Santa Maria, São Pedro e Sobral da Lagoa"
  },
  {
    "level": 2,
    "code": 1013,
    "name": "Pedrógão Grande"
  },
  {
    "level": 3,
    "code": 101301,
    "name": "Graça"
  },
  {
    "level": 3,
    "code": 101302,
    "name": "Pedrógão Grande"
  },
  {
    "level": 3,
    "code": 101303,
    "name": "Vila Facaia"
  },
  {
    "level": 2,
    "code": 1014,
    "name": "Peniche"
  },
  {
    "level": 3,
    "code": 101402,
    "name": "Atouguia da Baleia"
  },
  {
    "level": 3,
    "code": 101405,
    "name": "Serra d\'El-Rei"
  },
  {
    "level": 3,
    "code": 101406,
    "name": "Ferrel"
  },
  {
    "level": 3,
    "code": 101407,
    "name": "Peniche"
  },
  {
    "level": 2,
    "code": 1015,
    "name": "Pombal"
  },
  {
    "level": 3,
    "code": 101501,
    "name": "Abiul"
  },
  {
    "level": 3,
    "code": 101503,
    "name": "Almagreira"
  },
  {
    "level": 3,
    "code": 101504,
    "name": "Carnide"
  },
  {
    "level": 3,
    "code": 101505,
    "name": "Carriço"
  },
  {
    "level": 3,
    "code": 101506,
    "name": "Louriçal"
  },
  {
    "level": 3,
    "code": 101508,
    "name": "Pelariga"
  },
  {
    "level": 3,
    "code": 101509,
    "name": "Pombal"
  },
  {
    "level": 3,
    "code": 101510,
    "name": "Redinha"
  },
  {
    "level": 3,
    "code": 101513,
    "name": "Vermoil"
  },
  {
    "level": 3,
    "code": 101514,
    "name": "Vila Cã"
  },
  {
    "level": 3,
    "code": 101515,
    "name": "Meirinhas"
  },
  {
    "level": 3,
    "code": 101518,
    "name": "União das freguesias de Guia, Ilha e Mata Mourisca"
  },
  {
    "level": 3,
    "code": 101519,
    "name": "União das freguesias de Santiago e São Simão de Litém e Albergaria dos Doze"
  },
  {
    "level": 2,
    "code": 1016,
    "name": "Porto de Mós"
  },
  {
    "level": 3,
    "code": 101602,
    "name": "Alqueidão da Serra"
  },
  {
    "level": 3,
    "code": 101605,
    "name": "Calvaria de Cima"
  },
  {
    "level": 3,
    "code": 101606,
    "name": "Juncal"
  },
  {
    "level": 3,
    "code": 101608,
    "name": "Mira de Aire"
  },
  {
    "level": 3,
    "code": 101609,
    "name": "Pedreiras"
  },
  {
    "level": 3,
    "code": 101610,
    "name": "São Bento"
  },
  {
    "level": 3,
    "code": 101613,
    "name": "Serro Ventoso"
  },
  {
    "level": 3,
    "code": 101614,
    "name": "Porto de Mós - São João Baptista e São Pedro"
  },
  {
    "level": 3,
    "code": 101615,
    "name": "União das freguesias de Alvados e Alcaria"
  },
  {
    "level": 3,
    "code": 101616,
    "name": "União das freguesias de Arrimal e Mendiga"
  },
  {
    "level": 1,
    "code": 11,
    "name": "Lisboa"
  },
  {
    "level": 2,
    "code": 1101,
    "name": "Alenquer"
  },
  {
    "level": 3,
    "code": 110106,
    "name": "Carnota"
  },
  {
    "level": 3,
    "code": 110107,
    "name": "Meca"
  },
  {
    "level": 3,
    "code": 110108,
    "name": "Olhalvo"
  },
  {
    "level": 3,
    "code": 110109,
    "name": "Ota"
  },
  {
    "level": 3,
    "code": 110113,
    "name": "Ventosa"
  },
  {
    "level": 3,
    "code": 110114,
    "name": "Vila Verde dos Francos"
  },
  {
    "level": 3,
    "code": 110117,
    "name": "União das freguesias de Abrigada e Cabanas de Torres"
  },
  {
    "level": 3,
    "code": 110118,
    "name": "União das freguesias de Aldeia Galega da Merceana e Aldeia Gavinha"
  },
  {
    "level": 3,
    "code": 110119,
    "name": "União das freguesias de Alenquer (Santo Estêvão e Triana)"
  },
  {
    "level": 3,
    "code": 110120,
    "name": "União das freguesias de Carregado e Cadafais"
  },
  {
    "level": 3,
    "code": 110121,
    "name": "União das freguesias de Ribafria e Pereiro de Palhacana"
  },
  {
    "level": 2,
    "code": 1102,
    "name": "Arruda dos Vinhos"
  },
  {
    "level": 3,
    "code": 110201,
    "name": "Arranhó"
  },
  {
    "level": 3,
    "code": 110202,
    "name": "Arruda dos Vinhos"
  },
  {
    "level": 3,
    "code": 110203,
    "name": "Cardosas"
  },
  {
    "level": 3,
    "code": 110204,
    "name": "Santiago dos Velhos"
  },
  {
    "level": 2,
    "code": 1103,
    "name": "Azambuja"
  },
  {
    "level": 3,
    "code": 110301,
    "name": "Alcoentre"
  },
  {
    "level": 3,
    "code": 110302,
    "name": "Aveiras de Baixo"
  },
  {
    "level": 3,
    "code": 110303,
    "name": "Aveiras de Cima"
  },
  {
    "level": 3,
    "code": 110304,
    "name": "Azambuja"
  },
  {
    "level": 3,
    "code": 110306,
    "name": "Vale do Paraíso"
  },
  {
    "level": 3,
    "code": 110307,
    "name": "Vila Nova da Rainha"
  },
  {
    "level": 3,
    "code": 110310,
    "name": "União das freguesias de Manique do Intendente, Vila Nova de São Pedro e Maçussa"
  },
  {
    "level": 2,
    "code": 1104,
    "name": "Cadaval"
  },
  {
    "level": 3,
    "code": 110401,
    "name": "Alguber"
  },
  {
    "level": 3,
    "code": 110407,
    "name": "Peral"
  },
  {
    "level": 3,
    "code": 110409,
    "name": "Vermelha"
  },
  {
    "level": 3,
    "code": 110410,
    "name": "Vilar"
  },
  {
    "level": 3,
    "code": 110411,
    "name": "União das freguesias do Cadaval e Pêro Moniz"
  },
  {
    "level": 3,
    "code": 110412,
    "name": "União das freguesias de Lamas e Cercal"
  },
  {
    "level": 3,
    "code": 110413,
    "name": "União das freguesias de Painho e Figueiros"
  },
  {
    "level": 2,
    "code": 1105,
    "name": "Cascais"
  },
  {
    "level": 3,
    "code": 110501,
    "name": "Alcabideche"
  },
  {
    "level": 3,
    "code": 110506,
    "name": "São Domingos de Rana"
  },
  {
    "level": 3,
    "code": 110507,
    "name": "União das freguesias de Carcavelos e Parede"
  },
  {
    "level": 3,
    "code": 110508,
    "name": "União das freguesias de Cascais e Estoril"
  },
  {
    "level": 2,
    "code": 1106,
    "name": "Lisboa"
  },
  {
    "level": 3,
    "code": 110601,
    "name": "Ajuda"
  },
  {
    "level": 3,
    "code": 110602,
    "name": "Alcântara"
  },
  {
    "level": 3,
    "code": 110607,
    "name": "Beato"
  },
  {
    "level": 3,
    "code": 110608,
    "name": "Benfica"
  },
  {
    "level": 3,
    "code": 110610,
    "name": "Campolide"
  },
  {
    "level": 3,
    "code": 110611,
    "name": "Carnide"
  },
  {
    "level": 3,
    "code": 110618,
    "name": "Lumiar"
  },
  {
    "level": 3,
    "code": 110621,
    "name": "Marvila"
  },
  {
    "level": 3,
    "code": 110633,
    "name": "Olivais"
  },
  {
    "level": 3,
    "code": 110639,
    "name": "São Domingos de Benfica"
  },
  {
    "level": 3,
    "code": 110654,
    "name": "Alvalade"
  },
  {
    "level": 3,
    "code": 110655,
    "name": "Areeiro"
  },
  {
    "level": 3,
    "code": 110656,
    "name": "Arroios"
  },
  {
    "level": 3,
    "code": 110657,
    "name": "Avenidas Novas"
  },
  {
    "level": 3,
    "code": 110658,
    "name": "Belém"
  },
  {
    "level": 3,
    "code": 110659,
    "name": "Campo de Ourique"
  },
  {
    "level": 3,
    "code": 110660,
    "name": "Estrela"
  },
  {
    "level": 3,
    "code": 110661,
    "name": "Misericórdia"
  },
  {
    "level": 3,
    "code": 110662,
    "name": "Parque das Nações"
  },
  {
    "level": 3,
    "code": 110663,
    "name": "Penha de França"
  },
  {
    "level": 3,
    "code": 110664,
    "name": "Santa Clara"
  },
  {
    "level": 3,
    "code": 110665,
    "name": "Santa Maria Maior"
  },
  {
    "level": 3,
    "code": 110666,
    "name": "Santo António"
  },
  {
    "level": 3,
    "code": 110667,
    "name": "São Vicente"
  },
  {
    "level": 2,
    "code": 1107,
    "name": "Loures"
  },
  {
    "level": 3,
    "code": 110702,
    "name": "Bucelas"
  },
  {
    "level": 3,
    "code": 110705,
    "name": "Fanhões"
  },
  {
    "level": 3,
    "code": 110707,
    "name": "Loures"
  },
  {
    "level": 3,
    "code": 110708,
    "name": "Lousa"
  },
  {
    "level": 3,
    "code": 110726,
    "name": "União das freguesias de Moscavide e Portela"
  },
  {
    "level": 3,
    "code": 110727,
    "name": "União das freguesias de Sacavém e Prior Velho"
  },
  {
    "level": 3,
    "code": 110728,
    "name": "União das freguesias de Santa Iria de Azoia, São João da Talha e Bobadela"
  },
  {
    "level": 3,
    "code": 110729,
    "name": "União das freguesias de Santo Antão e São Julião do Tojal"
  },
  {
    "level": 3,
    "code": 110730,
    "name": "União das freguesias de Santo António dos Cavaleiros e Frielas"
  },
  {
    "level": 3,
    "code": 110731,
    "name": "União das freguesias de Camarate, Unhos e Apelação"
  },
  {
    "level": 2,
    "code": 1108,
    "name": "Lourinhã"
  },
  {
    "level": 3,
    "code": 110803,
    "name": "Moita dos Ferreiros"
  },
  {
    "level": 3,
    "code": 110805,
    "name": "Reguengo Grande"
  },
  {
    "level": 3,
    "code": 110806,
    "name": "Santa Bárbara"
  },
  {
    "level": 3,
    "code": 110808,
    "name": "Vimeiro"
  },
  {
    "level": 3,
    "code": 110810,
    "name": "Ribamar"
  },
  {
    "level": 3,
    "code": 110812,
    "name": "União das freguesias de Lourinhã e Atalaia"
  },
  {
    "level": 3,
    "code": 110813,
    "name": "União das freguesias de Miragaia e Marteleira"
  },
  {
    "level": 3,
    "code": 110814,
    "name": "União das freguesias de São Bartolomeu dos Galegos e Moledo"
  },
  {
    "level": 2,
    "code": 1109,
    "name": "Mafra"
  },
  {
    "level": 3,
    "code": 110902,
    "name": "Carvoeira"
  },
  {
    "level": 3,
    "code": 110904,
    "name": "Encarnação"
  },
  {
    "level": 3,
    "code": 110906,
    "name": "Ericeira"
  },
  {
    "level": 3,
    "code": 110909,
    "name": "Mafra"
  },
  {
    "level": 3,
    "code": 110911,
    "name": "Milharado"
  },
  {
    "level": 3,
    "code": 110913,
    "name": "Santo Isidoro"
  },
  {
    "level": 3,
    "code": 110918,
    "name": "União das freguesias de Azueira e Sobral da Abelheira"
  },
  {
    "level": 3,
    "code": 110919,
    "name": "União das freguesias de Enxara do Bispo, Gradil e Vila Franca do Rosário"
  },
  {
    "level": 3,
    "code": 110920,
    "name": "União das freguesias de Igreja Nova e Cheleiros"
  },
  {
    "level": 3,
    "code": 110921,
    "name": "União das freguesias de Malveira e São Miguel de Alcainça"
  },
  {
    "level": 3,
    "code": 110922,
    "name": "União das freguesias de Venda do Pinheiro e Santo Estêvão das Galés"
  },
  {
    "level": 2,
    "code": 1110,
    "name": "Oeiras"
  },
  {
    "level": 3,
    "code": 111002,
    "name": "Barcarena"
  },
  {
    "level": 3,
    "code": 111009,
    "name": "Porto Salvo"
  },
  {
    "level": 3,
    "code": 111012,
    "name": "União das freguesias de Algés, Linda-a-Velha e Cruz Quebrada-Dafundo"
  },
  {
    "level": 3,
    "code": 111013,
    "name": "União das freguesias de Carnaxide e Queijas"
  },
  {
    "level": 3,
    "code": 111014,
    "name": "União das freguesias de Oeiras e São Julião da Barra, Paço de Arcos e Caxias"
  },
  {
    "level": 2,
    "code": 1111,
    "name": "Sintra"
  },
  {
    "level": 3,
    "code": 111102,
    "name": "Algueirão-Mem Martins"
  },
  {
    "level": 3,
    "code": 111105,
    "name": "Colares"
  },
  {
    "level": 3,
    "code": 111108,
    "name": "Rio de Mouro"
  },
  {
    "level": 3,
    "code": 111115,
    "name": "Casal de Cambra"
  },
  {
    "level": 3,
    "code": 111122,
    "name": "União das freguesias de Agualva e Mira-Sintra"
  },
  {
    "level": 3,
    "code": 111123,
    "name": "União das freguesias de Almargem do Bispo, Pêro Pinheiro e Montelavar"
  },
  {
    "level": 3,
    "code": 111124,
    "name": "União das freguesias do Cacém e São Marcos"
  },
  {
    "level": 3,
    "code": 111125,
    "name": "União das freguesias de Massamá e Monte Abraão"
  },
  {
    "level": 3,
    "code": 111126,
    "name": "União das freguesias de Queluz e Belas"
  },
  {
    "level": 3,
    "code": 111127,
    "name": "União das freguesias de São João das Lampas e Terrugem"
  },
  {
    "level": 3,
    "code": 111128,
    "name": "União das freguesias de Sintra (Santa Maria e São Miguel, São Martinho e São Pedro de Penaferrim)"
  },
  {
    "level": 2,
    "code": 1112,
    "name": "Sobral de Monte Agraço"
  },
  {
    "level": 3,
    "code": 111201,
    "name": "Santo Quintino"
  },
  {
    "level": 3,
    "code": 111202,
    "name": "Sapataria"
  },
  {
    "level": 3,
    "code": 111203,
    "name": "Sobral de Monte Agraço"
  },
  {
    "level": 2,
    "code": 1113,
    "name": "Torres Vedras"
  },
  {
    "level": 3,
    "code": 111306,
    "name": "Freiria"
  },
  {
    "level": 3,
    "code": 111310,
    "name": "Ponte do Rol"
  },
  {
    "level": 3,
    "code": 111311,
    "name": "Ramalhal"
  },
  {
    "level": 3,
    "code": 111314,
    "name": "São Pedro da Cadeira"
  },
  {
    "level": 3,
    "code": 111316,
    "name": "Silveira"
  },
  {
    "level": 3,
    "code": 111317,
    "name": "Turcifal"
  },
  {
    "level": 3,
    "code": 111318,
    "name": "Ventosa"
  },
  {
    "level": 3,
    "code": 111321,
    "name": "União das freguesias de A dos Cunhados e Maceira"
  },
  {
    "level": 3,
    "code": 111322,
    "name": "União das freguesias de Campelos e Outeiro da Cabeça"
  },
  {
    "level": 3,
    "code": 111323,
    "name": "União das freguesias de Carvoeira e Carmões"
  },
  {
    "level": 3,
    "code": 111324,
    "name": "União das freguesias de Dois Portos e Runa"
  },
  {
    "level": 3,
    "code": 111325,
    "name": "União das freguesias de Maxial e Monte Redondo"
  },
  {
    "level": 3,
    "code": 111326,
    "name": "Santa Maria, São Pedro e Matacães"
  },
  {
    "level": 2,
    "code": 1114,
    "name": "Vila Franca de Xira"
  },
  {
    "level": 3,
    "code": 111408,
    "name": "Vialonga"
  },
  {
    "level": 3,
    "code": 111409,
    "name": "Vila Franca de Xira"
  },
  {
    "level": 3,
    "code": 111412,
    "name": "União das freguesias de Alhandra, São João dos Montes e Calhandriz"
  },
  {
    "level": 3,
    "code": 111413,
    "name": "União das freguesias de Alverca do Ribatejo e Sobralinho"
  },
  {
    "level": 3,
    "code": 111414,
    "name": "União das freguesias de Castanheira do Ribatejo e Cachoeiras"
  },
  {
    "level": 3,
    "code": 111415,
    "name": "União das freguesias de Póvoa de Santa Iria e Forte da Casa"
  },
  {
    "level": 2,
    "code": 1115,
    "name": "Amadora"
  },
  {
    "level": 3,
    "code": 111512,
    "name": "Alfragide"
  },
  {
    "level": 3,
    "code": 111513,
    "name": "Águas Livres"
  },
  {
    "level": 3,
    "code": 111514,
    "name": "Encosta do Sol"
  },
  {
    "level": 3,
    "code": 111515,
    "name": "Falagueira-Venda Nova"
  },
  {
    "level": 3,
    "code": 111516,
    "name": "Mina de Água"
  },
  {
    "level": 3,
    "code": 111517,
    "name": "Venteira"
  },
  {
    "level": 2,
    "code": 1116,
    "name": "Odivelas"
  },
  {
    "level": 3,
    "code": 111603,
    "name": "Odivelas"
  },
  {
    "level": 3,
    "code": 111608,
    "name": "União das freguesias de Pontinha e Famões"
  },
  {
    "level": 3,
    "code": 111609,
    "name": "União das freguesias de Póvoa de Santo Adrião e Olival Basto"
  },
  {
    "level": 3,
    "code": 111610,
    "name": "União das freguesias de Ramada e Caneças"
  },
  {
    "level": 1,
    "code": 12,
    "name": "Portalegre"
  },
  {
    "level": 2,
    "code": 1201,
    "name": "Alter do Chão"
  },
  {
    "level": 3,
    "code": 120101,
    "name": "Alter do Chão"
  },
  {
    "level": 3,
    "code": 120102,
    "name": "Chancelaria"
  },
  {
    "level": 3,
    "code": 120103,
    "name": "Seda"
  },
  {
    "level": 3,
    "code": 120104,
    "name": "Cunheira"
  },
  {
    "level": 2,
    "code": 1202,
    "name": "Arronches"
  },
  {
    "level": 3,
    "code": 120201,
    "name": "Assunção"
  },
  {
    "level": 3,
    "code": 120202,
    "name": "Esperança"
  },
  {
    "level": 3,
    "code": 120203,
    "name": "Mosteiros"
  },
  {
    "level": 2,
    "code": 1203,
    "name": "Avis"
  },
  {
    "level": 3,
    "code": 120302,
    "name": "Aldeia Velha"
  },
  {
    "level": 3,
    "code": 120303,
    "name": "Avis"
  },
  {
    "level": 3,
    "code": 120305,
    "name": "Ervedal"
  },
  {
    "level": 3,
    "code": 120306,
    "name": "Figueira e Barros"
  },
  {
    "level": 3,
    "code": 120309,
    "name": "União das freguesias de Alcórrego e Maranhão"
  },
  {
    "level": 3,
    "code": 120310,
    "name": "União das freguesias de Benavila e Valongo"
  },
  {
    "level": 2,
    "code": 1204,
    "name": "Campo Maior"
  },
  {
    "level": 3,
    "code": 120401,
    "name": "Nossa Senhora da Expectação"
  },
  {
    "level": 3,
    "code": 120402,
    "name": "Nossa Senhora da Graça dos Degolados"
  },
  {
    "level": 3,
    "code": 120403,
    "name": "São João Baptista"
  },
  {
    "level": 2,
    "code": 1205,
    "name": "Castelo de Vide"
  },
  {
    "level": 3,
    "code": 120501,
    "name": "Nossa Senhora da Graça de Póvoa e Meadas"
  },
  {
    "level": 3,
    "code": 120502,
    "name": "Santa Maria da Devesa"
  },
  {
    "level": 3,
    "code": 120503,
    "name": "Santiago Maior"
  },
  {
    "level": 3,
    "code": 120504,
    "name": "São João Baptista"
  },
  {
    "level": 2,
    "code": 1206,
    "name": "Crato"
  },
  {
    "level": 3,
    "code": 120601,
    "name": "Aldeia da Mata"
  },
  {
    "level": 3,
    "code": 120604,
    "name": "Gáfete"
  },
  {
    "level": 3,
    "code": 120605,
    "name": "Monte da Pedra"
  },
  {
    "level": 3,
    "code": 120607,
    "name": "União das freguesias de Crato e Mártires, Flor da Rosa e Vale do Peso"
  },
  {
    "level": 2,
    "code": 1207,
    "name": "Elvas"
  },
  {
    "level": 3,
    "code": 120706,
    "name": "Santa Eulália"
  },
  {
    "level": 3,
    "code": 120707,
    "name": "São Brás e São Lourenço"
  },
  {
    "level": 3,
    "code": 120708,
    "name": "São Vicente e Ventosa"
  },
  {
    "level": 3,
    "code": 120712,
    "name": "Assunção, Ajuda, Salvador e Santo Ildefonso"
  },
  {
    "level": 3,
    "code": 120713,
    "name": "Caia, São Pedro e Alcáçova"
  },
  {
    "level": 3,
    "code": 120714,
    "name": "União das freguesias de Barbacena e Vila Fernando"
  },
  {
    "level": 3,
    "code": 120715,
    "name": "União das freguesias de Terrugem e Vila Boim"
  },
  {
    "level": 2,
    "code": 1208,
    "name": "Fronteira"
  },
  {
    "level": 3,
    "code": 120801,
    "name": "Cabeço de Vide"
  },
  {
    "level": 3,
    "code": 120802,
    "name": "Fronteira"
  },
  {
    "level": 3,
    "code": 120803,
    "name": "São Saturnino"
  },
  {
    "level": 2,
    "code": 1209,
    "name": "Gavião"
  },
  {
    "level": 3,
    "code": 120902,
    "name": "Belver"
  },
  {
    "level": 3,
    "code": 120903,
    "name": "Comenda"
  },
  {
    "level": 3,
    "code": 120905,
    "name": "Margem"
  },
  {
    "level": 3,
    "code": 120906,
    "name": "União das freguesias de Gavião e Atalaia"
  },
  {
    "level": 2,
    "code": 1210,
    "name": "Marvão"
  },
  {
    "level": 3,
    "code": 121001,
    "name": "Beirã"
  },
  {
    "level": 3,
    "code": 121002,
    "name": "Santa Maria de Marvão"
  },
  {
    "level": 3,
    "code": 121003,
    "name": "Santo António das Areias"
  },
  {
    "level": 3,
    "code": 121004,
    "name": "São Salvador da Aramenha"
  },
  {
    "level": 2,
    "code": 1211,
    "name": "Monforte"
  },
  {
    "level": 3,
    "code": 121101,
    "name": "Assumar"
  },
  {
    "level": 3,
    "code": 121102,
    "name": "Monforte"
  },
  {
    "level": 3,
    "code": 121103,
    "name": "Santo Aleixo"
  },
  {
    "level": 3,
    "code": 121104,
    "name": "Vaiamonte"
  },
  {
    "level": 2,
    "code": 1212,
    "name": "Nisa"
  },
  {
    "level": 3,
    "code": 121201,
    "name": "Alpalhão"
  },
  {
    "level": 3,
    "code": 121205,
    "name": "Montalvão"
  },
  {
    "level": 3,
    "code": 121207,
    "name": "Santana"
  },
  {
    "level": 3,
    "code": 121208,
    "name": "São Matias"
  },
  {
    "level": 3,
    "code": 121210,
    "name": "Tolosa"
  },
  {
    "level": 3,
    "code": 121211,
    "name": "União das freguesias de Arez e Amieira do Tejo"
  },
  {
    "level": 3,
    "code": 121212,
    "name": "União das freguesias de Espírito Santo, Nossa Senhora da Graça e São Simão"
  },
  {
    "level": 2,
    "code": 1213,
    "name": "Ponte de Sor"
  },
  {
    "level": 3,
    "code": 121301,
    "name": "Galveias"
  },
  {
    "level": 3,
    "code": 121302,
    "name": "Montargil"
  },
  {
    "level": 3,
    "code": 121304,
    "name": "Foros de Arrão"
  },
  {
    "level": 3,
    "code": 121305,
    "name": "Longomel"
  },
  {
    "level": 3,
    "code": 121308,
    "name": "União das freguesias de Ponte de Sor, Tramaga e Vale de Açor"
  },
  {
    "level": 2,
    "code": 1214,
    "name": "Portalegre"
  },
  {
    "level": 3,
    "code": 121401,
    "name": "Alagoa"
  },
  {
    "level": 3,
    "code": 121402,
    "name": "Alegrete"
  },
  {
    "level": 3,
    "code": 121404,
    "name": "Fortios"
  },
  {
    "level": 3,
    "code": 121410,
    "name": "Urra"
  },
  {
    "level": 3,
    "code": 121411,
    "name": "União das freguesias da Sé e São Lourenço"
  },
  {
    "level": 3,
    "code": 121412,
    "name": "União das freguesias de Reguengo e São Julião"
  },
  {
    "level": 3,
    "code": 121413,
    "name": "União das freguesias de Ribeira de Nisa e Carreiras"
  },
  {
    "level": 2,
    "code": 1215,
    "name": "Sousel"
  },
  {
    "level": 3,
    "code": 121501,
    "name": "Cano"
  },
  {
    "level": 3,
    "code": 121502,
    "name": "Casa Branca"
  },
  {
    "level": 3,
    "code": 121503,
    "name": "Santo Amaro"
  },
  {
    "level": 3,
    "code": 121504,
    "name": "Sousel"
  },
  {
    "level": 1,
    "code": 13,
    "name": "Porto"
  },
  {
    "level": 2,
    "code": 1301,
    "name": "Amarante"
  },
  {
    "level": 3,
    "code": 130103,
    "name": "Ansiães"
  },
  {
    "level": 3,
    "code": 130107,
    "name": "Candemil"
  },
  {
    "level": 3,
    "code": 130112,
    "name": "Fregim"
  },
  {
    "level": 3,
    "code": 130115,
    "name": "Fridão"
  },
  {
    "level": 3,
    "code": 130117,
    "name": "Gondar"
  },
  {
    "level": 3,
    "code": 130118,
    "name": "Jazente"
  },
  {
    "level": 3,
    "code": 130119,
    "name": "Lomba"
  },
  {
    "level": 3,
    "code": 130120,
    "name": "Louredo"
  },
  {
    "level": 3,
    "code": 130121,
    "name": "Lufrei"
  },
  {
    "level": 3,
    "code": 130123,
    "name": "Mancelos"
  },
  {
    "level": 3,
    "code": 130126,
    "name": "Padronelo"
  },
  {
    "level": 3,
    "code": 130128,
    "name": "Rebordelo"
  },
  {
    "level": 3,
    "code": 130129,
    "name": "Salvador do Monte"
  },
  {
    "level": 3,
    "code": 130134,
    "name": "Gouveia (São Simão)"
  },
  {
    "level": 3,
    "code": 130135,
    "name": "Telões"
  },
  {
    "level": 3,
    "code": 130136,
    "name": "Travanca"
  },
  {
    "level": 3,
    "code": 130138,
    "name": "Vila Caiz"
  },
  {
    "level": 3,
    "code": 130139,
    "name": "Vila Chã do Marão"
  },
  {
    "level": 3,
    "code": 130141,
    "name": "União das freguesias de Aboadela, Sanche e Várzea"
  },
  {
    "level": 3,
    "code": 130142,
    "name": "União das freguesias de Amarante (São Gonçalo), Madalena, Cepelos e Gatão"
  },
  {
    "level": 3,
    "code": 130143,
    "name": "União das freguesias de Bustelo, Carneiro e Carvalho de Rei"
  },
  {
    "level": 3,
    "code": 130144,
    "name": "União das freguesias de Figueiró (Santiago e Santa Cristina)"
  },
  {
    "level": 3,
    "code": 130145,
    "name": "União das freguesias de Freixo de Cima e de Baixo"
  },
  {
    "level": 3,
    "code": 130146,
    "name": "União das freguesias de Olo e Canadelo"
  },
  {
    "level": 3,
    "code": 130147,
    "name": "Vila Meã"
  },
  {
    "level": 3,
    "code": 130148,
    "name": "União das freguesias de Vila Garcia, Aboim e Chapa"
  },
  {
    "level": 2,
    "code": 1302,
    "name": "Baião"
  },
  {
    "level": 3,
    "code": 130204,
    "name": "Frende"
  },
  {
    "level": 3,
    "code": 130205,
    "name": "Gestaçô"
  },
  {
    "level": 3,
    "code": 130206,
    "name": "Gove"
  },
  {
    "level": 3,
    "code": 130207,
    "name": "Grilo"
  },
  {
    "level": 3,
    "code": 130208,
    "name": "Loivos do Monte"
  },
  {
    "level": 3,
    "code": 130215,
    "name": "Santa Marinha do Zêzere"
  },
  {
    "level": 3,
    "code": 130219,
    "name": "Valadares"
  },
  {
    "level": 3,
    "code": 130220,
    "name": "Viariz"
  },
  {
    "level": 3,
    "code": 130221,
    "name": "União das freguesias de Ancede e Ribadouro"
  },
  {
    "level": 3,
    "code": 130222,
    "name": "União das freguesias de Baião (Santa Leocádia) e Mesquinhata"
  },
  {
    "level": 3,
    "code": 130223,
    "name": "União das freguesias de Campelo e Ovil"
  },
  {
    "level": 3,
    "code": 130224,
    "name": "União das freguesias de Loivos da Ribeira e Tresouras"
  },
  {
    "level": 3,
    "code": 130225,
    "name": "União das freguesias de Santa Cruz do Douro e São Tomé de Covelas"
  },
  {
    "level": 3,
    "code": 130226,
    "name": "União das freguesias de Teixeira e Teixeiró"
  },
  {
    "level": 2,
    "code": 1303,
    "name": "Felgueiras"
  },
  {
    "level": 3,
    "code": 130301,
    "name": "Aião"
  },
  {
    "level": 3,
    "code": 130302,
    "name": "Airães"
  },
  {
    "level": 3,
    "code": 130305,
    "name": "Friande"
  },
  {
    "level": 3,
    "code": 130306,
    "name": "Idães"
  },
  {
    "level": 3,
    "code": 130307,
    "name": "Jugueiros"
  },
  {
    "level": 3,
    "code": 130313,
    "name": "Penacova"
  },
  {
    "level": 3,
    "code": 130314,
    "name": "Pinheiro"
  },
  {
    "level": 3,
    "code": 130315,
    "name": "Pombeiro de Ribavizela"
  },
  {
    "level": 3,
    "code": 130317,
    "name": "Refontoura"
  },
  {
    "level": 3,
    "code": 130318,
    "name": "Regilde"
  },
  {
    "level": 3,
    "code": 130319,
    "name": "Revinhade"
  },
  {
    "level": 3,
    "code": 130324,
    "name": "Sendim"
  },
  {
    "level": 3,
    "code": 130334,
    "name": "União das freguesias de Macieira da Lixa e Caramos"
  },
  {
    "level": 3,
    "code": 130335,
    "name": "União das freguesias de Margaride (Santa Eulália), Várzea, Lagares, Varziela e Moure"
  },
  {
    "level": 3,
    "code": 130336,
    "name": "União das freguesias de Pedreira, Rande e Sernande"
  },
  {
    "level": 3,
    "code": 130337,
    "name": "União das freguesias de Torrados e Sousa"
  },
  {
    "level": 3,
    "code": 130338,
    "name": "União das freguesias de Unhão e Lordelo"
  },
  {
    "level": 3,
    "code": 130339,
    "name": "União das freguesias de Vila Cova da Lixa e Borba de Godim"
  },
  {
    "level": 3,
    "code": 130340,
    "name": "União das freguesias de Vila Fria e Vizela (São Jorge)"
  },
  {
    "level": 3,
    "code": 130341,
    "name": "União das freguesias de Vila Verde e Santão"
  },
  {
    "level": 2,
    "code": 1304,
    "name": "Gondomar"
  },
  {
    "level": 3,
    "code": 130405,
    "name": "Lomba"
  },
  {
    "level": 3,
    "code": 130408,
    "name": "Rio Tinto"
  },
  {
    "level": 3,
    "code": 130412,
    "name": "Baguim do Monte (Rio Tinto)"
  },
  {
    "level": 3,
    "code": 130413,
    "name": "União das freguesias de Fânzeres e São Pedro da Cova"
  },
  {
    "level": 3,
    "code": 130414,
    "name": "União das freguesias de Foz do Sousa e Covelo"
  },
  {
    "level": 3,
    "code": 130415,
    "name": "União das freguesias de Gondomar (São Cosme), Valbom e Jovim"
  },
  {
    "level": 3,
    "code": 130416,
    "name": "União das freguesias de Melres e Medas"
  },
  {
    "level": 2,
    "code": 1305,
    "name": "Lousada"
  },
  {
    "level": 3,
    "code": 130502,
    "name": "Aveleda"
  },
  {
    "level": 3,
    "code": 130504,
    "name": "Caíde de Rei"
  },
  {
    "level": 3,
    "code": 130510,
    "name": "Lodares"
  },
  {
    "level": 3,
    "code": 130512,
    "name": "Macieira"
  },
  {
    "level": 3,
    "code": 130513,
    "name": "Meinedo"
  },
  {
    "level": 3,
    "code": 130515,
    "name": "Nevogilde"
  },
  {
    "level": 3,
    "code": 130524,
    "name": "Sousela"
  },
  {
    "level": 3,
    "code": 130525,
    "name": "Torno"
  },
  {
    "level": 3,
    "code": 130526,
    "name": "Vilar do Torno e Alentém"
  },
  {
    "level": 3,
    "code": 130527,
    "name": "União das freguesias de Cernadelo e Lousada (São Miguel e Santa Margarida)"
  },
  {
    "level": 3,
    "code": 130528,
    "name": "União das freguesias de Cristelos, Boim e Ordem"
  },
  {
    "level": 3,
    "code": 130529,
    "name": "União das freguesias de Figueiras e Covas"
  },
  {
    "level": 3,
    "code": 130530,
    "name": "União das freguesias de Lustosa e Barrosas (Santo Estêvão)"
  },
  {
    "level": 3,
    "code": 130531,
    "name": "União das freguesias de Nespereira e Casais"
  },
  {
    "level": 3,
    "code": 130532,
    "name": "União das freguesias de Silvares, Pias, Nogueira e Alvarenga"
  },
  {
    "level": 2,
    "code": 1306,
    "name": "Maia"
  },
  {
    "level": 3,
    "code": 130601,
    "name": "Águas Santas"
  },
  {
    "level": 3,
    "code": 130603,
    "name": "Folgosa"
  },
  {
    "level": 3,
    "code": 130608,
    "name": "Milheirós"
  },
  {
    "level": 3,
    "code": 130609,
    "name": "Moreira"
  },
  {
    "level": 3,
    "code": 130613,
    "name": "São Pedro Fins"
  },
  {
    "level": 3,
    "code": 130616,
    "name": "Vila Nova da Telha"
  },
  {
    "level": 3,
    "code": 130617,
    "name": "Pedrouços"
  },
  {
    "level": 3,
    "code": 130618,
    "name": "Castêlo da Maia"
  },
  {
    "level": 3,
    "code": 130619,
    "name": "Cidade da Maia"
  },
  {
    "level": 3,
    "code": 130620,
    "name": "Nogueira e Silva Escura"
  },
  {
    "level": 2,
    "code": 1307,
    "name": "Marco de Canaveses"
  },
  {
    "level": 3,
    "code": 130704,
    "name": "Banho e Carvalhosa"
  },
  {
    "level": 3,
    "code": 130705,
    "name": "Constance"
  },
  {
    "level": 3,
    "code": 130722,
    "name": "Soalhães"
  },
  {
    "level": 3,
    "code": 130723,
    "name": "Sobretâmega"
  },
  {
    "level": 3,
    "code": 130724,
    "name": "Tabuado"
  },
  {
    "level": 3,
    "code": 130730,
    "name": "Vila Boa do Bispo"
  },
  {
    "level": 3,
    "code": 130732,
    "name": "Alpendorada, Várzea e Torrão"
  },
  {
    "level": 3,
    "code": 130733,
    "name": "Avessadas e Rosém"
  },
  {
    "level": 3,
    "code": 130734,
    "name": "Bem Viver"
  },
  {
    "level": 3,
    "code": 130735,
    "name": "Santo Isidoro e Livração"
  },
  {
    "level": 3,
    "code": 130736,
    "name": "Marco"
  },
  {
    "level": 3,
    "code": 130737,
    "name": "Paredes de Viadores e Manhuncelos"
  },
  {
    "level": 3,
    "code": 130738,
    "name": "Penhalonga e Paços de Gaiolo"
  },
  {
    "level": 3,
    "code": 130739,
    "name": "Sande e São Lourenço do Douro"
  },
  {
    "level": 3,
    "code": 130740,
    "name": "Várzea, Aliviada e Folhada"
  },
  {
    "level": 3,
    "code": 130741,
    "name": "Vila Boa de Quires e Maureles"
  },
  {
    "level": 2,
    "code": 1308,
    "name": "Matosinhos"
  },
  {
    "level": 3,
    "code": 130811,
    "name": "União das freguesias de Custóias, Leça do Balio e Guifões"
  },
  {
    "level": 3,
    "code": 130812,
    "name": "União das freguesias de Matosinhos e Leça da Palmeira"
  },
  {
    "level": 3,
    "code": 130813,
    "name": "União das freguesias de Perafita, Lavra e Santa Cruz do Bispo"
  },
  {
    "level": 3,
    "code": 130814,
    "name": "União das freguesias de São Mamede de Infesta e Senhora da Hora"
  },
  {
    "level": 2,
    "code": 1309,
    "name": "Paços de Ferreira"
  },
  {
    "level": 3,
    "code": 130902,
    "name": "Carvalhosa"
  },
  {
    "level": 3,
    "code": 130904,
    "name": "Eiriz"
  },
  {
    "level": 3,
    "code": 130905,
    "name": "Ferreira"
  },
  {
    "level": 3,
    "code": 130906,
    "name": "Figueiró"
  },
  {
    "level": 3,
    "code": 130908,
    "name": "Freamunde"
  },
  {
    "level": 3,
    "code": 130910,
    "name": "Meixomil"
  },
  {
    "level": 3,
    "code": 130913,
    "name": "Penamaior"
  },
  {
    "level": 3,
    "code": 130914,
    "name": "Raimonda"
  },
  {
    "level": 3,
    "code": 130916,
    "name": "Seroa"
  },
  {
    "level": 3,
    "code": 130917,
    "name": "Frazão Arreigada"
  },
  {
    "level": 3,
    "code": 130918,
    "name": "Paços de Ferreira"
  },
  {
    "level": 3,
    "code": 130919,
    "name": "Sanfins Lamoso Codessos"
  },
  {
    "level": 2,
    "code": 1310,
    "name": "Paredes"
  },
  {
    "level": 3,
    "code": 131001,
    "name": "Aguiar de Sousa"
  },
  {
    "level": 3,
    "code": 131002,
    "name": "Astromil"
  },
  {
    "level": 3,
    "code": 131003,
    "name": "Baltar"
  },
  {
    "level": 3,
    "code": 131004,
    "name": "Beire"
  },
  {
    "level": 3,
    "code": 131008,
    "name": "Cete"
  },
  {
    "level": 3,
    "code": 131009,
    "name": "Cristelo"
  },
  {
    "level": 3,
    "code": 131010,
    "name": "Duas Igrejas"
  },
  {
    "level": 3,
    "code": 131011,
    "name": "Gandra"
  },
  {
    "level": 3,
    "code": 131013,
    "name": "Lordelo"
  },
  {
    "level": 3,
    "code": 131014,
    "name": "Louredo"
  },
  {
    "level": 3,
    "code": 131017,
    "name": "Parada de Todeia"
  },
  {
    "level": 3,
    "code": 131018,
    "name": "Rebordosa"
  },
  {
    "level": 3,
    "code": 131019,
    "name": "Recarei"
  },
  {
    "level": 3,
    "code": 131020,
    "name": "Sobreira"
  },
  {
    "level": 3,
    "code": 131021,
    "name": "Sobrosa"
  },
  {
    "level": 3,
    "code": 131022,
    "name": "Vandoma"
  },
  {
    "level": 3,
    "code": 131024,
    "name": "Vilela"
  },
  {
    "level": 3,
    "code": 131025,
    "name": "Paredes"
  },
  {
    "level": 2,
    "code": 1311,
    "name": "Penafiel"
  },
  {
    "level": 3,
    "code": 131101,
    "name": "Abragão"
  },
  {
    "level": 3,
    "code": 131102,
    "name": "Boelhe"
  },
  {
    "level": 3,
    "code": 131103,
    "name": "Bustelo"
  },
  {
    "level": 3,
    "code": 131104,
    "name": "Cabeça Santa"
  },
  {
    "level": 3,
    "code": 131105,
    "name": "Canelas"
  },
  {
    "level": 3,
    "code": 131106,
    "name": "Capela"
  },
  {
    "level": 3,
    "code": 131107,
    "name": "Castelões"
  },
  {
    "level": 3,
    "code": 131108,
    "name": "Croca"
  },
  {
    "level": 3,
    "code": 131109,
    "name": "Duas Igrejas"
  },
  {
    "level": 3,
    "code": 131110,
    "name": "Eja"
  },
  {
    "level": 3,
    "code": 131112,
    "name": "Fonte Arcada"
  },
  {
    "level": 3,
    "code": 131113,
    "name": "Galegos"
  },
  {
    "level": 3,
    "code": 131115,
    "name": "Irivo"
  },
  {
    "level": 3,
    "code": 131121,
    "name": "Oldrões"
  },
  {
    "level": 3,
    "code": 131122,
    "name": "Paço de Sousa"
  },
  {
    "level": 3,
    "code": 131125,
    "name": "Perozelo"
  },
  {
    "level": 3,
    "code": 131128,
    "name": "Rans"
  },
  {
    "level": 3,
    "code": 131129,
    "name": "Rio de Moinhos"
  },
  {
    "level": 3,
    "code": 131132,
    "name": "Recezinhos (São Mamede)"
  },
  {
    "level": 3,
    "code": 131133,
    "name": "Recezinhos (São Martinho)"
  },
  {
    "level": 3,
    "code": 131134,
    "name": "Sebolido"
  },
  {
    "level": 3,
    "code": 131136,
    "name": "Valpedre"
  },
  {
    "level": 3,
    "code": 131138,
    "name": "Rio Mau"
  },
  {
    "level": 3,
    "code": 131139,
    "name": "Penafiel"
  },
  {
    "level": 3,
    "code": 131140,
    "name": "Luzim e Vila Cova"
  },
  {
    "level": 3,
    "code": 131141,
    "name": "Guilhufe e Urrô"
  },
  {
    "level": 3,
    "code": 131142,
    "name": "Lagares e Figueira"
  },
  {
    "level": 3,
    "code": 131143,
    "name": "Termas de São Vicente"
  },
  {
    "level": 2,
    "code": 1312,
    "name": "Porto"
  },
  {
    "level": 3,
    "code": 131202,
    "name": "Bonfim"
  },
  {
    "level": 3,
    "code": 131203,
    "name": "Campanhã"
  },
  {
    "level": 3,
    "code": 131210,
    "name": "Paranhos"
  },
  {
    "level": 3,
    "code": 131211,
    "name": "Ramalde"
  },
  {
    "level": 3,
    "code": 131216,
    "name": "União das freguesias de Aldoar, Foz do Douro e Nevogilde"
  },
  {
    "level": 3,
    "code": 131217,
    "name": "União das freguesias de Cedofeita, Santo Ildefonso, Sé, Miragaia, São Nicolau e Vitória"
  },
  {
    "level": 3,
    "code": 131218,
    "name": "União das freguesias de Lordelo do Ouro e Massarelos"
  },
  {
    "level": 2,
    "code": 1313,
    "name": "Póvoa de Varzim"
  },
  {
    "level": 3,
    "code": 131305,
    "name": "Balazar"
  },
  {
    "level": 3,
    "code": 131307,
    "name": "Estela"
  },
  {
    "level": 3,
    "code": 131308,
    "name": "Laundos"
  },
  {
    "level": 3,
    "code": 131311,
    "name": "Rates"
  },
  {
    "level": 3,
    "code": 131313,
    "name": "União das freguesias de Aver-o-Mar, Amorim e Terroso"
  },
  {
    "level": 3,
    "code": 131314,
    "name": "União das freguesias de Aguçadoura e Navais"
  },
  {
    "level": 3,
    "code": 131315,
    "name": "União das freguesias da Póvoa de Varzim, Beiriz e Argivai"
  },
  {
    "level": 2,
    "code": 1314,
    "name": "Santo Tirso"
  },
  {
    "level": 3,
    "code": 131401,
    "name": "Agrela"
  },
  {
    "level": 3,
    "code": 131402,
    "name": "Água Longa"
  },
  {
    "level": 3,
    "code": 131405,
    "name": "Aves"
  },
  {
    "level": 3,
    "code": 131413,
    "name": "Monte Córdova"
  },
  {
    "level": 3,
    "code": 131416,
    "name": "Rebordões"
  },
  {
    "level": 3,
    "code": 131418,
    "name": "Reguenga"
  },
  {
    "level": 3,
    "code": 131419,
    "name": "Roriz"
  },
  {
    "level": 3,
    "code": 131430,
    "name": "Negrelos (São Tomé)"
  },
  {
    "level": 3,
    "code": 131432,
    "name": "Vilarinho"
  },
  {
    "level": 3,
    "code": 131433,
    "name": "União das freguesias de Areias, Sequeiró, Lama e Palmeira"
  },
  {
    "level": 3,
    "code": 131434,
    "name": "Vila Nova do Campo"
  },
  {
    "level": 3,
    "code": 131435,
    "name": "União das freguesias de Carreira e Refojos de Riba de Ave"
  },
  {
    "level": 3,
    "code": 131436,
    "name": "União das freguesias de Lamelas e Guimarei"
  },
  {
    "level": 3,
    "code": 131437,
    "name": "União das freguesias de Santo Tirso, Couto (Santa Cristina e São Miguel) e Burgães"
  },
  {
    "level": 2,
    "code": 1315,
    "name": "Valongo"
  },
  {
    "level": 3,
    "code": 131501,
    "name": "Alfena"
  },
  {
    "level": 3,
    "code": 131503,
    "name": "Ermesinde"
  },
  {
    "level": 3,
    "code": 131505,
    "name": "Valongo"
  },
  {
    "level": 3,
    "code": 131506,
    "name": "União das freguesias de Campo e Sobrado"
  },
  {
    "level": 2,
    "code": 1316,
    "name": "Vila do Conde"
  },
  {
    "level": 3,
    "code": 131602,
    "name": "Árvore"
  },
  {
    "level": 3,
    "code": 131603,
    "name": "Aveleda"
  },
  {
    "level": 3,
    "code": 131604,
    "name": "Azurara"
  },
  {
    "level": 3,
    "code": 131607,
    "name": "Fajozes"
  },
  {
    "level": 3,
    "code": 131610,
    "name": "Gião"
  },
  {
    "level": 3,
    "code": 131611,
    "name": "Guilhabreu"
  },
  {
    "level": 3,
    "code": 131612,
    "name": "Junqueira"
  },
  {
    "level": 3,
    "code": 131613,
    "name": "Labruge"
  },
  {
    "level": 3,
    "code": 131614,
    "name": "Macieira da Maia"
  },
  {
    "level": 3,
    "code": 131616,
    "name": "Mindelo"
  },
  {
    "level": 3,
    "code": 131617,
    "name": "Modivas"
  },
  {
    "level": 3,
    "code": 131627,
    "name": "Vila Chã"
  },
  {
    "level": 3,
    "code": 131628,
    "name": "Vila do Conde"
  },
  {
    "level": 3,
    "code": 131630,
    "name": "Vilar de Pinheiro"
  },
  {
    "level": 3,
    "code": 131631,
    "name": "União das freguesias de Bagunte, Ferreiró, Outeiro Maior e Parada"
  },
  {
    "level": 3,
    "code": 131632,
    "name": "União das freguesias de Fornelo e Vairão"
  },
  {
    "level": 3,
    "code": 131633,
    "name": "União das freguesias de Malta e Canidelo"
  },
  {
    "level": 3,
    "code": 131634,
    "name": "União das freguesias de Retorta e Tougues"
  },
  {
    "level": 3,
    "code": 131635,
    "name": "União das freguesias de Rio Mau e Arcos"
  },
  {
    "level": 3,
    "code": 131636,
    "name": "União das freguesias de Touguinha e Touguinhó"
  },
  {
    "level": 3,
    "code": 131637,
    "name": "União das freguesias de Vilar e Mosteiró"
  },
  {
    "level": 2,
    "code": 1317,
    "name": "Vila Nova de Gaia"
  },
  {
    "level": 3,
    "code": 131701,
    "name": "Arcozelo"
  },
  {
    "level": 3,
    "code": 131702,
    "name": "Avintes"
  },
  {
    "level": 3,
    "code": 131703,
    "name": "Canelas"
  },
  {
    "level": 3,
    "code": 131704,
    "name": "Canidelo"
  },
  {
    "level": 3,
    "code": 131709,
    "name": "Madalena"
  },
  {
    "level": 3,
    "code": 131712,
    "name": "Oliveira do Douro"
  },
  {
    "level": 3,
    "code": 131717,
    "name": "São Félix da Marinha"
  },
  {
    "level": 3,
    "code": 131723,
    "name": "Vilar de Andorinho"
  },
  {
    "level": 3,
    "code": 131725,
    "name": "União das freguesias de Grijó e Sermonde"
  },
  {
    "level": 3,
    "code": 131726,
    "name": "União das freguesias de Gulpilhares e Valadares"
  },
  {
    "level": 3,
    "code": 131727,
    "name": "União das freguesias de Mafamude e Vilar do Paraíso"
  },
  {
    "level": 3,
    "code": 131728,
    "name": "União das freguesias de Pedroso e Seixezelo"
  },
  {
    "level": 3,
    "code": 131729,
    "name": "União das freguesias de Sandim, Olival, Lever e Crestuma"
  },
  {
    "level": 3,
    "code": 131730,
    "name": "União das freguesias de Santa Marinha e São Pedro da Afurada"
  },
  {
    "level": 3,
    "code": 131731,
    "name": "União das freguesias de Serzedo e Perosinho"
  },
  {
    "level": 2,
    "code": 1318,
    "name": "Trofa"
  },
  {
    "level": 3,
    "code": 131806,
    "name": "Covelas"
  },
  {
    "level": 3,
    "code": 131808,
    "name": "Muro"
  },
  {
    "level": 3,
    "code": 131809,
    "name": "União das freguesias de Alvarelhos e Guidões"
  },
  {
    "level": 3,
    "code": 131810,
    "name": "União das freguesias de Bougado (São Martinho e Santiago)"
  },
  {
    "level": 3,
    "code": 131811,
    "name": "União das freguesias de Coronado (São Romão e São Mamede)"
  },
  {
    "level": 1,
    "code": 14,
    "name": "Santarém"
  },
  {
    "level": 2,
    "code": 1401,
    "name": "Abrantes"
  },
  {
    "level": 3,
    "code": 140104,
    "name": "Bemposta"
  },
  {
    "level": 3,
    "code": 140105,
    "name": "Martinchel"
  },
  {
    "level": 3,
    "code": 140106,
    "name": "Mouriscas"
  },
  {
    "level": 3,
    "code": 140107,
    "name": "Pego"
  },
  {
    "level": 3,
    "code": 140108,
    "name": "Rio de Moinhos"
  },
  {
    "level": 3,
    "code": 140115,
    "name": "Tramagal"
  },
  {
    "level": 3,
    "code": 140118,
    "name": "Fontes"
  },
  {
    "level": 3,
    "code": 140119,
    "name": "Carvalhal"
  },
  {
    "level": 3,
    "code": 140120,
    "name": "União das freguesias de Abrantes (São Vicente e São João) e Alferrarede"
  },
  {
    "level": 3,
    "code": 140121,
    "name": "União das freguesias de Aldeia do Mato e Souto"
  },
  {
    "level": 3,
    "code": 140122,
    "name": "União das freguesias de Alvega e Concavada"
  },
  {
    "level": 3,
    "code": 140123,
    "name": "União das freguesias de São Facundo e Vale das Mós"
  },
  {
    "level": 3,
    "code": 140124,
    "name": "União das freguesias de São Miguel do Rio Torto e Rossio ao Sul do Tejo"
  },
  {
    "level": 2,
    "code": 1402,
    "name": "Alcanena"
  },
  {
    "level": 3,
    "code": 140202,
    "name": "Bugalhos"
  },
  {
    "level": 3,
    "code": 140206,
    "name": "Minde"
  },
  {
    "level": 3,
    "code": 140207,
    "name": "Moitas Venda"
  },
  {
    "level": 3,
    "code": 140208,
    "name": "Monsanto"
  },
  {
    "level": 3,
    "code": 140209,
    "name": "Serra de Santo António"
  },
  {
    "level": 3,
    "code": 140211,
    "name": "União das freguesias de Alcanena e Vila Moreira"
  },
  {
    "level": 3,
    "code": 140212,
    "name": "União das freguesias de Malhou, Louriceira e Espinheiro"
  },
  {
    "level": 2,
    "code": 1403,
    "name": "Almeirim"
  },
  {
    "level": 3,
    "code": 140301,
    "name": "Almeirim"
  },
  {
    "level": 3,
    "code": 140302,
    "name": "Benfica do Ribatejo"
  },
  {
    "level": 3,
    "code": 140303,
    "name": "Fazendas de Almeirim"
  },
  {
    "level": 3,
    "code": 140304,
    "name": "Raposa"
  },
  {
    "level": 2,
    "code": 1404,
    "name": "Alpiarça"
  },
  {
    "level": 3,
    "code": 140401,
    "name": "Alpiarça"
  },
  {
    "level": 2,
    "code": 1405,
    "name": "Benavente"
  },
  {
    "level": 3,
    "code": 140501,
    "name": "Benavente"
  },
  {
    "level": 3,
    "code": 140502,
    "name": "Samora Correia"
  },
  {
    "level": 3,
    "code": 140503,
    "name": "Santo Estêvão"
  },
  {
    "level": 3,
    "code": 140504,
    "name": "Barrosa"
  },
  {
    "level": 2,
    "code": 1406,
    "name": "Cartaxo"
  },
  {
    "level": 3,
    "code": 140604,
    "name": "Pontével"
  },
  {
    "level": 3,
    "code": 140605,
    "name": "Valada"
  },
  {
    "level": 3,
    "code": 140607,
    "name": "Vila Chã de Ourique"
  },
  {
    "level": 3,
    "code": 140608,
    "name": "Vale da Pedra"
  },
  {
    "level": 3,
    "code": 140609,
    "name": "União das freguesias do Cartaxo e Vale da Pinta"
  },
  {
    "level": 3,
    "code": 140610,
    "name": "União das freguesias de Ereira e Lapa"
  },
  {
    "level": 2,
    "code": 1407,
    "name": "Chamusca"
  },
  {
    "level": 3,
    "code": 140704,
    "name": "Ulme"
  },
  {
    "level": 3,
    "code": 140705,
    "name": "Vale de Cavalos"
  },
  {
    "level": 3,
    "code": 140707,
    "name": "Carregueira"
  },
  {
    "level": 3,
    "code": 140708,
    "name": "União das freguesias da Chamusca e Pinheiro Grande"
  },
  {
    "level": 3,
    "code": 140709,
    "name": "União das freguesias de Parreira e Chouto"
  },
  {
    "level": 2,
    "code": 1408,
    "name": "Constância"
  },
  {
    "level": 3,
    "code": 140801,
    "name": "Constância"
  },
  {
    "level": 3,
    "code": 140802,
    "name": "Montalvo"
  },
  {
    "level": 3,
    "code": 140803,
    "name": "Santa Margarida da Coutada"
  },
  {
    "level": 2,
    "code": 1409,
    "name": "Coruche"
  },
  {
    "level": 3,
    "code": 140902,
    "name": "Couço"
  },
  {
    "level": 3,
    "code": 140903,
    "name": "São José da Lamarosa"
  },
  {
    "level": 3,
    "code": 140905,
    "name": "Branca"
  },
  {
    "level": 3,
    "code": 140907,
    "name": "Biscainho"
  },
  {
    "level": 3,
    "code": 140908,
    "name": "Santana do Mato"
  },
  {
    "level": 3,
    "code": 140909,
    "name": "União das freguesias de Coruche, Fajarda e Erra"
  },
  {
    "level": 2,
    "code": 1410,
    "name": "Entroncamento"
  },
  {
    "level": 3,
    "code": 141001,
    "name": "São João Baptista"
  },
  {
    "level": 3,
    "code": 141002,
    "name": "Nossa Senhora de Fátima"
  },
  {
    "level": 2,
    "code": 1411,
    "name": "Ferreira do Zêzere"
  },
  {
    "level": 3,
    "code": 141101,
    "name": "Águas Belas"
  },
  {
    "level": 3,
    "code": 141103,
    "name": "Beco"
  },
  {
    "level": 3,
    "code": 141104,
    "name": "Chãos"
  },
  {
    "level": 3,
    "code": 141106,
    "name": "Ferreira do Zêzere"
  },
  {
    "level": 3,
    "code": 141107,
    "name": "Igreja Nova do Sobral"
  },
  {
    "level": 3,
    "code": 141110,
    "name": "Nossa Senhora do Pranto"
  },
  {
    "level": 3,
    "code": 141111,
    "name": "União das freguesias de Areias e Pias"
  },
  {
    "level": 2,
    "code": 1412,
    "name": "Golegã"
  },
  {
    "level": 3,
    "code": 141201,
    "name": "Azinhaga"
  },
  {
    "level": 3,
    "code": 141202,
    "name": "Golegã"
  },
  {
    "level": 3,
    "code": 141203,
    "name": "Pombalinho"
  },
  {
    "level": 2,
    "code": 1413,
    "name": "Mação"
  },
  {
    "level": 3,
    "code": 141302,
    "name": "Amêndoa"
  },
  {
    "level": 3,
    "code": 141303,
    "name": "Cardigos"
  },
  {
    "level": 3,
    "code": 141304,
    "name": "Carvoeiro"
  },
  {
    "level": 3,
    "code": 141305,
    "name": "Envendos"
  },
  {
    "level": 3,
    "code": 141307,
    "name": "Ortiga"
  },
  {
    "level": 3,
    "code": 141309,
    "name": "União das freguesias de Mação, Penhascoso e Aboboreira"
  },
  {
    "level": 2,
    "code": 1414,
    "name": "Rio Maior"
  },
  {
    "level": 3,
    "code": 141401,
    "name": "Alcobertas"
  },
  {
    "level": 3,
    "code": 141402,
    "name": "Arrouquelas"
  },
  {
    "level": 3,
    "code": 141405,
    "name": "Fráguas"
  },
  {
    "level": 3,
    "code": 141408,
    "name": "Rio Maior"
  },
  {
    "level": 3,
    "code": 141410,
    "name": "Asseiceira"
  },
  {
    "level": 3,
    "code": 141411,
    "name": "São Sebastião"
  },
  {
    "level": 3,
    "code": 141415,
    "name": "União das freguesias de Azambujeira e Malaqueijo"
  },
  {
    "level": 3,
    "code": 141416,
    "name": "União das freguesias de Marmeleira e Assentiz"
  },
  {
    "level": 3,
    "code": 141417,
    "name": "União das freguesias de Outeiro da Cortiçada e Arruda dos Pisões"
  },
  {
    "level": 3,
    "code": 141418,
    "name": "União das freguesias de São João da Ribeira e Ribeira de São João"
  },
  {
    "level": 2,
    "code": 1415,
    "name": "Salvaterra de Magos"
  },
  {
    "level": 3,
    "code": 141502,
    "name": "Marinhais"
  },
  {
    "level": 3,
    "code": 141503,
    "name": "Muge"
  },
  {
    "level": 3,
    "code": 141507,
    "name": "União das freguesias de Glória do Ribatejo e Granho"
  },
  {
    "level": 3,
    "code": 141508,
    "name": "União das freguesias de Salvaterra de Magos e Foros de Salvaterra"
  },
  {
    "level": 2,
    "code": 1416,
    "name": "Santarém"
  },
  {
    "level": 3,
    "code": 141601,
    "name": "Abitureiras"
  },
  {
    "level": 3,
    "code": 141602,
    "name": "Abrã"
  },
  {
    "level": 3,
    "code": 141604,
    "name": "Alcanede"
  },
  {
    "level": 3,
    "code": 141605,
    "name": "Alcanhões"
  },
  {
    "level": 3,
    "code": 141606,
    "name": "Almoster"
  },
  {
    "level": 3,
    "code": 141607,
    "name": "Amiais de Baixo"
  },
  {
    "level": 3,
    "code": 141608,
    "name": "Arneiro das Milhariças"
  },
  {
    "level": 3,
    "code": 141613,
    "name": "Moçarria"
  },
  {
    "level": 3,
    "code": 141614,
    "name": "Pernes"
  },
  {
    "level": 3,
    "code": 141616,
    "name": "Póvoa da Isenta"
  },
  {
    "level": 3,
    "code": 141625,
    "name": "Vale de Santarém"
  },
  {
    "level": 3,
    "code": 141628,
    "name": "Gançaria"
  },
  {
    "level": 3,
    "code": 141629,
    "name": "União das freguesias de Achete, Azoia de Baixo e Póvoa de Santarém"
  },
  {
    "level": 3,
    "code": 141630,
    "name": "União das freguesias de Azoia de Cima e Tremês"
  },
  {
    "level": 3,
    "code": 141631,
    "name": "União das freguesias de Casével e Vaqueiros"
  },
  {
    "level": 3,
    "code": 141632,
    "name": "União das freguesias de Romeira e Várzea"
  },
  {
    "level": 3,
    "code": 141633,
    "name": "União das freguesias de Santarém (Marvila), Santa Iria da Ribeira de Santarém, Santarém (São Salvador) e Santarém (São Nicolau)"
  },
  {
    "level": 3,
    "code": 141634,
    "name": "União das freguesias de São Vicente do Paul e Vale de Figueira"
  },
  {
    "level": 2,
    "code": 1417,
    "name": "Sardoal"
  },
  {
    "level": 3,
    "code": 141701,
    "name": "Alcaravela"
  },
  {
    "level": 3,
    "code": 141702,
    "name": "Santiago de Montalegre"
  },
  {
    "level": 3,
    "code": 141703,
    "name": "Sardoal"
  },
  {
    "level": 3,
    "code": 141704,
    "name": "Valhascos"
  },
  {
    "level": 2,
    "code": 1418,
    "name": "Tomar"
  },
  {
    "level": 3,
    "code": 141802,
    "name": "Asseiceira"
  },
  {
    "level": 3,
    "code": 141804,
    "name": "Carregueiros"
  },
  {
    "level": 3,
    "code": 141808,
    "name": "Olalhas"
  },
  {
    "level": 3,
    "code": 141809,
    "name": "Paialvo"
  },
  {
    "level": 3,
    "code": 141813,
    "name": "São Pedro de Tomar"
  },
  {
    "level": 3,
    "code": 141814,
    "name": "Sabacheira"
  },
  {
    "level": 3,
    "code": 141817,
    "name": "União das freguesias de Além da Ribeira e Pedreira"
  },
  {
    "level": 3,
    "code": 141818,
    "name": "União das freguesias de Casais e Alviobeira"
  },
  {
    "level": 3,
    "code": 141819,
    "name": "União das freguesias de Madalena e Beselga"
  },
  {
    "level": 3,
    "code": 141820,
    "name": "União das freguesias de Serra e Junceira"
  },
  {
    "level": 3,
    "code": 141821,
    "name": "União das freguesias de Tomar (São João Baptista) e Santa Maria dos Olivais"
  },
  {
    "level": 2,
    "code": 1419,
    "name": "Torres Novas"
  },
  {
    "level": 3,
    "code": 141902,
    "name": "Assentiz"
  },
  {
    "level": 3,
    "code": 141904,
    "name": "Chancelaria"
  },
  {
    "level": 3,
    "code": 141909,
    "name": "Pedrógão"
  },
  {
    "level": 3,
    "code": 141910,
    "name": "Riachos"
  },
  {
    "level": 3,
    "code": 141916,
    "name": "Zibreira"
  },
  {
    "level": 3,
    "code": 141917,
    "name": "Meia Via"
  },
  {
    "level": 3,
    "code": 141918,
    "name": "União das freguesias de Brogueira, Parceiros de Igreja e Alcorochel"
  },
  {
    "level": 3,
    "code": 141919,
    "name": "União das freguesias de Olaia e Paço"
  },
  {
    "level": 3,
    "code": 141920,
    "name": "União das freguesias de Torres Novas (Santa Maria, Salvador e Santiago)"
  },
  {
    "level": 3,
    "code": 141921,
    "name": "União das freguesias de Torres Novas (São Pedro), Lapas e Ribeira Branca"
  },
  {
    "level": 2,
    "code": 1420,
    "name": "Vila Nova da Barquinha"
  },
  {
    "level": 3,
    "code": 142001,
    "name": "Atalaia"
  },
  {
    "level": 3,
    "code": 142002,
    "name": "Praia do Ribatejo"
  },
  {
    "level": 3,
    "code": 142003,
    "name": "Tancos"
  },
  {
    "level": 3,
    "code": 142006,
    "name": "Vila Nova da Barquinha"
  },
  {
    "level": 2,
    "code": 1421,
    "name": "Ourém"
  },
  {
    "level": 3,
    "code": 142101,
    "name": "Alburitel"
  },
  {
    "level": 3,
    "code": 142102,
    "name": "Atouguia"
  },
  {
    "level": 3,
    "code": 142104,
    "name": "Caxarias"
  },
  {
    "level": 3,
    "code": 142105,
    "name": "Espite"
  },
  {
    "level": 3,
    "code": 142106,
    "name": "Fátima"
  },
  {
    "level": 3,
    "code": 142111,
    "name": "Nossa Senhora das Misericórdias"
  },
  {
    "level": 3,
    "code": 142113,
    "name": "Seiça"
  },
  {
    "level": 3,
    "code": 142114,
    "name": "Urqueira"
  },
  {
    "level": 3,
    "code": 142115,
    "name": "Nossa Senhora da Piedade"
  },
  {
    "level": 3,
    "code": 142119,
    "name": "União das freguesias de Freixianda, Ribeira do Fárrio e Formigais"
  },
  {
    "level": 3,
    "code": 142120,
    "name": "União das freguesias de Gondemaria e Olival"
  },
  {
    "level": 3,
    "code": 142121,
    "name": "União das freguesias de Matas e Cercal"
  },
  {
    "level": 3,
    "code": 142122,
    "name": "União das freguesias de Rio de Couros e Casal dos Bernardos"
  },
  {
    "level": 1,
    "code": 15,
    "name": "Setúbal"
  },
  {
    "level": 2,
    "code": 1501,
    "name": "Alcácer do Sal"
  },
  {
    "level": 3,
    "code": 150104,
    "name": "Torrão"
  },
  {
    "level": 3,
    "code": 150105,
    "name": "São Martinho"
  },
  {
    "level": 3,
    "code": 150106,
    "name": "Comporta"
  },
  {
    "level": 3,
    "code": 150107,
    "name": "União das freguesias de Alcácer do Sal (Santa Maria do Castelo e Santiago) e Santa Susana"
  },
  {
    "level": 2,
    "code": 1502,
    "name": "Alcochete"
  },
  {
    "level": 3,
    "code": 150201,
    "name": "Alcochete"
  },
  {
    "level": 3,
    "code": 150202,
    "name": "Samouco"
  },
  {
    "level": 3,
    "code": 150203,
    "name": "São Francisco"
  },
  {
    "level": 2,
    "code": 1503,
    "name": "Almada"
  },
  {
    "level": 3,
    "code": 150303,
    "name": "Costa da Caparica"
  },
  {
    "level": 3,
    "code": 150312,
    "name": "União das freguesias de Almada, Cova da Piedade, Pragal e Cacilhas"
  },
  {
    "level": 3,
    "code": 150313,
    "name": "União das freguesias de Caparica e Trafaria"
  },
  {
    "level": 3,
    "code": 150314,
    "name": "União das freguesias de Charneca de Caparica e Sobreda"
  },
  {
    "level": 3,
    "code": 150315,
    "name": "União das freguesias de Laranjeiro e Feijó"
  },
  {
    "level": 2,
    "code": 1504,
    "name": "Barreiro"
  },
  {
    "level": 3,
    "code": 150407,
    "name": "Santo António da Charneca"
  },
  {
    "level": 3,
    "code": 150409,
    "name": "União das freguesias de Alto do Seixalinho, Santo André e Verderena"
  },
  {
    "level": 3,
    "code": 150410,
    "name": "União das freguesias de Barreiro e Lavradio"
  },
  {
    "level": 3,
    "code": 150411,
    "name": "União das freguesias de Palhais e Coina"
  },
  {
    "level": 2,
    "code": 1505,
    "name": "Grândola"
  },
  {
    "level": 3,
    "code": 150501,
    "name": "Azinheira dos Barros e São Mamede do Sádão"
  },
  {
    "level": 3,
    "code": 150503,
    "name": "Melides"
  },
  {
    "level": 3,
    "code": 150505,
    "name": "Carvalhal"
  },
  {
    "level": 3,
    "code": 150506,
    "name": "União das freguesias de Grândola e Santa Margarida da Serra"
  },
  {
    "level": 2,
    "code": 1506,
    "name": "Moita"
  },
  {
    "level": 3,
    "code": 150601,
    "name": "Alhos Vedros"
  },
  {
    "level": 3,
    "code": 150603,
    "name": "Moita"
  },
  {
    "level": 3,
    "code": 150607,
    "name": "União das freguesias de Baixa da Banheira e Vale da Amoreira"
  },
  {
    "level": 3,
    "code": 150608,
    "name": "União das freguesias de Gaio-Rosário e Sarilhos Pequenos"
  },
  {
    "level": 2,
    "code": 1507,
    "name": "Montijo"
  },
  {
    "level": 3,
    "code": 150701,
    "name": "Canha"
  },
  {
    "level": 3,
    "code": 150704,
    "name": "Sarilhos Grandes"
  },
  {
    "level": 3,
    "code": 150709,
    "name": "União das freguesias de Atalaia e Alto Estanqueiro-Jardia"
  },
  {
    "level": 3,
    "code": 150710,
    "name": "União das freguesias de Montijo e Afonsoeiro"
  },
  {
    "level": 3,
    "code": 150711,
    "name": "União das freguesias de Pegões"
  },
  {
    "level": 2,
    "code": 1508,
    "name": "Palmela"
  },
  {
    "level": 3,
    "code": 150802,
    "name": "Palmela"
  },
  {
    "level": 3,
    "code": 150803,
    "name": "Pinhal Novo"
  },
  {
    "level": 3,
    "code": 150804,
    "name": "Quinta do Anjo"
  },
  {
    "level": 3,
    "code": 150806,
    "name": "União das freguesias de Poceirão e Marateca"
  },
  {
    "level": 2,
    "code": 1509,
    "name": "Santiago do Cacém"
  },
  {
    "level": 3,
    "code": 150901,
    "name": "Abela"
  },
  {
    "level": 3,
    "code": 150902,
    "name": "Alvalade"
  },
  {
    "level": 3,
    "code": 150903,
    "name": "Cercal"
  },
  {
    "level": 3,
    "code": 150904,
    "name": "Ermidas-Sado"
  },
  {
    "level": 3,
    "code": 150907,
    "name": "Santo André"
  },
  {
    "level": 3,
    "code": 150910,
    "name": "São Francisco da Serra"
  },
  {
    "level": 3,
    "code": 150912,
    "name": "União das freguesias de Santiago do Cacém, Santa Cruz e São Bartolomeu da Serra"
  },
  {
    "level": 3,
    "code": 150913,
    "name": "União das freguesias de São Domingos e Vale de Água"
  },
  {
    "level": 2,
    "code": 1510,
    "name": "Seixal"
  },
  {
    "level": 3,
    "code": 151002,
    "name": "Amora"
  },
  {
    "level": 3,
    "code": 151005,
    "name": "Corroios"
  },
  {
    "level": 3,
    "code": 151006,
    "name": "Fernão Ferro"
  },
  {
    "level": 3,
    "code": 151007,
    "name": "União das freguesias do Seixal, Arrentela e Aldeia de Paio Pires"
  },
  {
    "level": 2,
    "code": 1511,
    "name": "Sesimbra"
  },
  {
    "level": 3,
    "code": 151101,
    "name": "Sesimbra (Castelo)"
  },
  {
    "level": 3,
    "code": 151102,
    "name": "Sesimbra (Santiago)"
  },
  {
    "level": 3,
    "code": 151103,
    "name": "Quinta do Conde"
  },
  {
    "level": 2,
    "code": 1512,
    "name": "Setúbal"
  },
  {
    "level": 3,
    "code": 151205,
    "name": "Setúbal (São Sebastião)"
  },
  {
    "level": 3,
    "code": 151207,
    "name": "Gâmbia-Pontes-Alto da Guerra"
  },
  {
    "level": 3,
    "code": 151208,
    "name": "Sado"
  },
  {
    "level": 3,
    "code": 151209,
    "name": "União das freguesias de Azeitão (São Lourenço e São Simão)"
  },
  {
    "level": 3,
    "code": 151210,
    "name": "União das freguesias de Setúbal (São Julião, Nossa Senhora da Anunciada e Santa Maria da Graça)"
  },
  {
    "level": 2,
    "code": 1513,
    "name": "Sines"
  },
  {
    "level": 3,
    "code": 151301,
    "name": "Sines"
  },
  {
    "level": 3,
    "code": 151302,
    "name": "Porto Covo"
  },
  {
    "level": 1,
    "code": 16,
    "name": "Viana do Castelo"
  },
  {
    "level": 2,
    "code": 1601,
    "name": "Arcos de Valdevez"
  },
  {
    "level": 3,
    "code": 160101,
    "name": "Aboim das Choças"
  },
  {
    "level": 3,
    "code": 160102,
    "name": "Aguiã"
  },
  {
    "level": 3,
    "code": 160104,
    "name": "Ázere"
  },
  {
    "level": 3,
    "code": 160105,
    "name": "Cabana Maior"
  },
  {
    "level": 3,
    "code": 160106,
    "name": "Cabreiro"
  },
  {
    "level": 3,
    "code": 160108,
    "name": "Cendufe"
  },
  {
    "level": 3,
    "code": 160109,
    "name": "Couto"
  },
  {
    "level": 3,
    "code": 160113,
    "name": "Gavieira"
  },
  {
    "level": 3,
    "code": 160115,
    "name": "Gondoriz"
  },
  {
    "level": 3,
    "code": 160121,
    "name": "Miranda"
  },
  {
    "level": 3,
    "code": 160122,
    "name": "Monte Redondo"
  },
  {
    "level": 3,
    "code": 160123,
    "name": "Oliveira"
  },
  {
    "level": 3,
    "code": 160124,
    "name": "Paçô"
  },
  {
    "level": 3,
    "code": 160125,
    "name": "Padroso"
  },
  {
    "level": 3,
    "code": 160128,
    "name": "Prozelo"
  },
  {
    "level": 3,
    "code": 160130,
    "name": "Rio Frio"
  },
  {
    "level": 3,
    "code": 160131,
    "name": "Rio de Moinhos"
  },
  {
    "level": 3,
    "code": 160133,
    "name": "Sabadim"
  },
  {
    "level": 3,
    "code": 160142,
    "name": "Jolda (São Paio)"
  },
  {
    "level": 3,
    "code": 160144,
    "name": "Senharei"
  },
  {
    "level": 3,
    "code": 160145,
    "name": "Sistelo"
  },
  {
    "level": 3,
    "code": 160146,
    "name": "Soajo"
  },
  {
    "level": 3,
    "code": 160149,
    "name": "Vale"
  },
  {
    "level": 3,
    "code": 160152,
    "name": "União das freguesias de Alvora e Loureda"
  },
  {
    "level": 3,
    "code": 160153,
    "name": "União das freguesias de Arcos de Valdevez (São Paio) e Giela"
  },
  {
    "level": 3,
    "code": 160154,
    "name": "União das freguesias de Arcos de Valdevez (Salvador), Vila Fonche e Parada"
  },
  {
    "level": 3,
    "code": 160155,
    "name": "União das freguesias de Eiras e Mei"
  },
  {
    "level": 3,
    "code": 160156,
    "name": "União das freguesias de Grade e Carralcova"
  },
  {
    "level": 3,
    "code": 160157,
    "name": "União das freguesias de Guilhadeses e Santar"
  },
  {
    "level": 3,
    "code": 160158,
    "name": "União das freguesias de Jolda (Madalena) e Rio Cabrão"
  },
  {
    "level": 3,
    "code": 160159,
    "name": "União das freguesias de Padreiro (Salvador e Santa Cristina)"
  },
  {
    "level": 3,
    "code": 160160,
    "name": "União das freguesias de Portela e Extremo"
  },
  {
    "level": 3,
    "code": 160161,
    "name": "União das freguesias de São Jorge e Ermelo"
  },
  {
    "level": 3,
    "code": 160162,
    "name": "União das freguesias de Souto e Tabaçô"
  },
  {
    "level": 3,
    "code": 160163,
    "name": "União das freguesias de Távora (Santa Maria e São Vicente)"
  },
  {
    "level": 3,
    "code": 160164,
    "name": "União das freguesias de Vilela, São Cosme e São Damião e Sá"
  },
  {
    "level": 2,
    "code": 1602,
    "name": "Caminha"
  },
  {
    "level": 3,
    "code": 160201,
    "name": "Âncora"
  },
  {
    "level": 3,
    "code": 160205,
    "name": "Argela"
  },
  {
    "level": 3,
    "code": 160209,
    "name": "Dem"
  },
  {
    "level": 3,
    "code": 160211,
    "name": "Lanhelas"
  },
  {
    "level": 3,
    "code": 160214,
    "name": "Riba de Âncora"
  },
  {
    "level": 3,
    "code": 160215,
    "name": "Seixas"
  },
  {
    "level": 3,
    "code": 160217,
    "name": "Vila Praia de Âncora"
  },
  {
    "level": 3,
    "code": 160218,
    "name": "Vilar de Mouros"
  },
  {
    "level": 3,
    "code": 160220,
    "name": "Vile"
  },
  {
    "level": 3,
    "code": 160221,
    "name": "União das freguesias de Arga (Baixo, Cima e São João)"
  },
  {
    "level": 3,
    "code": 160222,
    "name": "União das freguesias de Caminha (Matriz) e Vilarelho"
  },
  {
    "level": 3,
    "code": 160223,
    "name": "União das freguesias de Gondar e Orbacém"
  },
  {
    "level": 3,
    "code": 160224,
    "name": "União das freguesias de Moledo e Cristelo"
  },
  {
    "level": 3,
    "code": 160225,
    "name": "União das freguesias de Venade e Azevedo"
  },
  {
    "level": 2,
    "code": 1603,
    "name": "Melgaço"
  },
  {
    "level": 3,
    "code": 160301,
    "name": "Alvaredo"
  },
  {
    "level": 3,
    "code": 160304,
    "name": "Cousso"
  },
  {
    "level": 3,
    "code": 160305,
    "name": "Cristoval"
  },
  {
    "level": 3,
    "code": 160307,
    "name": "Fiães"
  },
  {
    "level": 3,
    "code": 160308,
    "name": "Gave"
  },
  {
    "level": 3,
    "code": 160311,
    "name": "Paderne"
  },
  {
    "level": 3,
    "code": 160313,
    "name": "Penso"
  },
  {
    "level": 3,
    "code": 160317,
    "name": "São Paio"
  },
  {
    "level": 3,
    "code": 160319,
    "name": "União das freguesias de Castro Laboreiro e Lamas de Mouro"
  },
  {
    "level": 3,
    "code": 160320,
    "name": "União das freguesias de Chaviães e Paços"
  },
  {
    "level": 3,
    "code": 160321,
    "name": "União das freguesias de Parada do Monte e Cubalhão"
  },
  {
    "level": 3,
    "code": 160322,
    "name": "União das freguesias de Prado e Remoães"
  },
  {
    "level": 3,
    "code": 160323,
    "name": "União das freguesias de Vila e Roussas"
  },
  {
    "level": 2,
    "code": 1604,
    "name": "Monção"
  },
  {
    "level": 3,
    "code": 160401,
    "name": "Abedim"
  },
  {
    "level": 3,
    "code": 160404,
    "name": "Barbeita"
  },
  {
    "level": 3,
    "code": 160405,
    "name": "Barroças e Taias"
  },
  {
    "level": 3,
    "code": 160406,
    "name": "Bela"
  },
  {
    "level": 3,
    "code": 160407,
    "name": "Cambeses"
  },
  {
    "level": 3,
    "code": 160410,
    "name": "Lara"
  },
  {
    "level": 3,
    "code": 160411,
    "name": "Longos Vales"
  },
  {
    "level": 3,
    "code": 160415,
    "name": "Merufe"
  },
  {
    "level": 3,
    "code": 160418,
    "name": "Moreira"
  },
  {
    "level": 3,
    "code": 160420,
    "name": "Pias"
  },
  {
    "level": 3,
    "code": 160421,
    "name": "Pinheiros"
  },
  {
    "level": 3,
    "code": 160422,
    "name": "Podame"
  },
  {
    "level": 3,
    "code": 160423,
    "name": "Portela"
  },
  {
    "level": 3,
    "code": 160424,
    "name": "Riba de Mouro"
  },
  {
    "level": 3,
    "code": 160427,
    "name": "Segude"
  },
  {
    "level": 3,
    "code": 160428,
    "name": "Tangil"
  },
  {
    "level": 3,
    "code": 160431,
    "name": "Trute"
  },
  {
    "level": 3,
    "code": 160434,
    "name": "União das freguesias de Anhões e Luzio"
  },
  {
    "level": 3,
    "code": 160435,
    "name": "União das freguesias de Ceivães e Badim"
  },
  {
    "level": 3,
    "code": 160436,
    "name": "União das freguesias de Mazedo e Cortes"
  },
  {
    "level": 3,
    "code": 160437,
    "name": "União das freguesias de Messegães, Valadares e Sá"
  },
  {
    "level": 3,
    "code": 160438,
    "name": "União das freguesias de Monção e Troviscoso"
  },
  {
    "level": 3,
    "code": 160439,
    "name": "União das freguesias de Sago, Lordelo e Parada"
  },
  {
    "level": 3,
    "code": 160440,
    "name": "União das freguesias de Troporiz e Lapela"
  },
  {
    "level": 2,
    "code": 1605,
    "name": "Paredes de Coura"
  },
  {
    "level": 3,
    "code": 160501,
    "name": "Agualonga"
  },
  {
    "level": 3,
    "code": 160503,
    "name": "Castanheira"
  },
  {
    "level": 3,
    "code": 160505,
    "name": "Coura"
  },
  {
    "level": 3,
    "code": 160507,
    "name": "Cunha"
  },
  {
    "level": 3,
    "code": 160510,
    "name": "Infesta"
  },
  {
    "level": 3,
    "code": 160513,
    "name": "Mozelos"
  },
  {
    "level": 3,
    "code": 160514,
    "name": "Padornelo"
  },
  {
    "level": 3,
    "code": 160515,
    "name": "Parada"
  },
  {
    "level": 3,
    "code": 160519,
    "name": "Romarigães"
  },
  {
    "level": 3,
    "code": 160520,
    "name": "Rubiães"
  },
  {
    "level": 3,
    "code": 160521,
    "name": "Vascões"
  },
  {
    "level": 3,
    "code": 160522,
    "name": "União das freguesias de Bico e Cristelo"
  },
  {
    "level": 3,
    "code": 160523,
    "name": "União das freguesias de Cossourado e Linhares"
  },
  {
    "level": 3,
    "code": 160524,
    "name": "União das freguesias de Formariz e Ferreira"
  },
  {
    "level": 3,
    "code": 160525,
    "name": "União das freguesias de Insalde e Porreiras"
  },
  {
    "level": 3,
    "code": 160526,
    "name": "União das freguesias de Paredes de Coura e Resende"
  },
  {
    "level": 2,
    "code": 1606,
    "name": "Ponte da Barca"
  },
  {
    "level": 3,
    "code": 160601,
    "name": "Azias"
  },
  {
    "level": 3,
    "code": 160602,
    "name": "Boivães"
  },
  {
    "level": 3,
    "code": 160603,
    "name": "Bravães"
  },
  {
    "level": 3,
    "code": 160604,
    "name": "Britelo"
  },
  {
    "level": 3,
    "code": 160606,
    "name": "Cuide de Vila Verde"
  },
  {
    "level": 3,
    "code": 160611,
    "name": "Lavradas"
  },
  {
    "level": 3,
    "code": 160612,
    "name": "Lindoso"
  },
  {
    "level": 3,
    "code": 160613,
    "name": "Nogueira"
  },
  {
    "level": 3,
    "code": 160614,
    "name": "Oleiros"
  },
  {
    "level": 3,
    "code": 160619,
    "name": "Sampriz"
  },
  {
    "level": 3,
    "code": 160623,
    "name": "Vade (São Pedro)"
  },
  {
    "level": 3,
    "code": 160624,
    "name": "Vade (São Tomé)"
  },
  {
    "level": 3,
    "code": 160626,
    "name": "União das freguesias de Crasto, Ruivos e Grovelas"
  },
  {
    "level": 3,
    "code": 160627,
    "name": "União das freguesias de Entre Ambos-os-Rios, Ermida e Germil"
  },
  {
    "level": 3,
    "code": 160628,
    "name": "União das freguesias de Ponte da Barca, Vila Nova de Muía e Paço Vedro de Magalhães"
  },
  {
    "level": 3,
    "code": 160629,
    "name": "União das freguesias de Touvedo (São Lourenço e Salvador)"
  },
  {
    "level": 3,
    "code": 160630,
    "name": "União das freguesias de Vila Chã (São João Baptista e Santiago)"
  },
  {
    "level": 2,
    "code": 1607,
    "name": "Ponte de Lima"
  },
  {
    "level": 3,
    "code": 160701,
    "name": "Anais"
  },
  {
    "level": 3,
    "code": 160703,
    "name": "São Pedro d\'Arcos"
  },
  {
    "level": 3,
    "code": 160704,
    "name": "Arcozelo"
  },
  {
    "level": 3,
    "code": 160707,
    "name": "Beiral do Lima"
  },
  {
    "level": 3,
    "code": 160708,
    "name": "Bertiandos"
  },
  {
    "level": 3,
    "code": 160709,
    "name": "Boalhosa"
  },
  {
    "level": 3,
    "code": 160710,
    "name": "Brandara"
  },
  {
    "level": 3,
    "code": 160713,
    "name": "Calheiros"
  },
  {
    "level": 3,
    "code": 160714,
    "name": "Calvelo"
  },
  {
    "level": 3,
    "code": 160716,
    "name": "Correlhã"
  },
  {
    "level": 3,
    "code": 160717,
    "name": "Estorãos"
  },
  {
    "level": 3,
    "code": 160718,
    "name": "Facha"
  },
  {
    "level": 3,
    "code": 160719,
    "name": "Feitosa"
  },
  {
    "level": 3,
    "code": 160721,
    "name": "Fontão"
  },
  {
    "level": 3,
    "code": 160724,
    "name": "Friastelas"
  },
  {
    "level": 3,
    "code": 160726,
    "name": "Gandra"
  },
  {
    "level": 3,
    "code": 160727,
    "name": "Gemieira"
  },
  {
    "level": 3,
    "code": 160728,
    "name": "Gondufe"
  },
  {
    "level": 3,
    "code": 160729,
    "name": "Labruja"
  },
  {
    "level": 3,
    "code": 160734,
    "name": "Poiares"
  },
  {
    "level": 3,
    "code": 160737,
    "name": "Refóios do Lima"
  },
  {
    "level": 3,
    "code": 160739,
    "name": "Ribeira"
  },
  {
    "level": 3,
    "code": 160740,
    "name": "Sá"
  },
  {
    "level": 3,
    "code": 160742,
    "name": "Santa Comba"
  },
  {
    "level": 3,
    "code": 160743,
    "name": "Santa Cruz do Lima"
  },
  {
    "level": 3,
    "code": 160744,
    "name": "Rebordões (Santa Maria)"
  },
  {
    "level": 3,
    "code": 160745,
    "name": "Seara"
  },
  {
    "level": 3,
    "code": 160746,
    "name": "Serdedelo"
  },
  {
    "level": 3,
    "code": 160747,
    "name": "Rebordões (Souto)"
  },
  {
    "level": 3,
    "code": 160750,
    "name": "Vitorino das Donas"
  },
  {
    "level": 3,
    "code": 160752,
    "name": "Arca e Ponte de Lima"
  },
  {
    "level": 3,
    "code": 160753,
    "name": "Ardegão, Freixo e Mato"
  },
  {
    "level": 3,
    "code": 160754,
    "name": "Associação de freguesias do Vale do Neiva"
  },
  {
    "level": 3,
    "code": 160755,
    "name": "Bárrio e Cepões"
  },
  {
    "level": 3,
    "code": 160756,
    "name": "Cabaços e Fojo Lobal"
  },
  {
    "level": 3,
    "code": 160757,
    "name": "Cabração e Moreira do Lima"
  },
  {
    "level": 3,
    "code": 160758,
    "name": "Fornelos e Queijada"
  },
  {
    "level": 3,
    "code": 160759,
    "name": "Labrujó, Rendufe e Vilar do Monte"
  },
  {
    "level": 3,
    "code": 160760,
    "name": "Navió e Vitorino dos Piães"
  },
  {
    "level": 2,
    "code": 1608,
    "name": "Valença"
  },
  {
    "level": 3,
    "code": 160802,
    "name": "Boivão"
  },
  {
    "level": 3,
    "code": 160803,
    "name": "Cerdal"
  },
  {
    "level": 3,
    "code": 160805,
    "name": "Fontoura"
  },
  {
    "level": 3,
    "code": 160806,
    "name": "Friestas"
  },
  {
    "level": 3,
    "code": 160808,
    "name": "Ganfei"
  },
  {
    "level": 3,
    "code": 160812,
    "name": "São Pedro da Torre"
  },
  {
    "level": 3,
    "code": 160816,
    "name": "Verdoejo"
  },
  {
    "level": 3,
    "code": 160817,
    "name": "União das freguesias de Gandra e Taião"
  },
  {
    "level": 3,
    "code": 160818,
    "name": "União das freguesias de Gondomil e Sanfins"
  },
  {
    "level": 3,
    "code": 160819,
    "name": "União das freguesias de São Julião e Silva"
  },
  {
    "level": 3,
    "code": 160820,
    "name": "União das freguesias de Valença, Cristelo Covo e Arão"
  },
  {
    "level": 2,
    "code": 1609,
    "name": "Viana do Castelo"
  },
  {
    "level": 3,
    "code": 160901,
    "name": "Afife"
  },
  {
    "level": 3,
    "code": 160902,
    "name": "Alvarães"
  },
  {
    "level": 3,
    "code": 160903,
    "name": "Amonde"
  },
  {
    "level": 3,
    "code": 160904,
    "name": "Anha"
  },
  {
    "level": 3,
    "code": 160905,
    "name": "Areosa"
  },
  {
    "level": 3,
    "code": 160908,
    "name": "Carreço"
  },
  {
    "level": 3,
    "code": 160910,
    "name": "Castelo do Neiva"
  },
  {
    "level": 3,
    "code": 160911,
    "name": "Darque"
  },
  {
    "level": 3,
    "code": 160914,
    "name": "Freixieiro de Soutelo"
  },
  {
    "level": 3,
    "code": 160915,
    "name": "Lanheses"
  },
  {
    "level": 3,
    "code": 160920,
    "name": "Montaria"
  },
  {
    "level": 3,
    "code": 160922,
    "name": "Mujães"
  },
  {
    "level": 3,
    "code": 160923,
    "name": "São Romão de Neiva"
  },
  {
    "level": 3,
    "code": 160925,
    "name": "Outeiro"
  },
  {
    "level": 3,
    "code": 160926,
    "name": "Perre"
  },
  {
    "level": 3,
    "code": 160928,
    "name": "Santa Marta de Portuzelo"
  },
  {
    "level": 3,
    "code": 160935,
    "name": "Vila Franca"
  },
  {
    "level": 3,
    "code": 160938,
    "name": "Vila de Punhe"
  },
  {
    "level": 3,
    "code": 160940,
    "name": "Chafé"
  },
  {
    "level": 3,
    "code": 160941,
    "name": "União das freguesias de Barroselas e Carvoeiro"
  },
  {
    "level": 3,
    "code": 160942,
    "name": "União das freguesias de Cardielos e Serreleis"
  },
  {
    "level": 3,
    "code": 160943,
    "name": "União das freguesias de Geraz do Lima (Santa Maria, Santa Leocádia e Moreira) e Deão"
  },
  {
    "level": 3,
    "code": 160944,
    "name": "União das freguesias de Mazarefes e Vila Fria"
  },
  {
    "level": 3,
    "code": 160945,
    "name": "União das freguesias de Nogueira, Meixedo e Vilar de Murteda"
  },
  {
    "level": 3,
    "code": 160946,
    "name": "União das freguesias de Subportela, Deocriste e Portela Susã"
  },
  {
    "level": 3,
    "code": 160947,
    "name": "União das freguesias de Torre e Vila Mou"
  },
  {
    "level": 3,
    "code": 160948,
    "name": "União das freguesias de Viana do Castelo (Santa Maria Maior e Monserrate) e Meadela"
  },
  {
    "level": 2,
    "code": 1610,
    "name": "Vila Nova de Cerveira"
  },
  {
    "level": 3,
    "code": 161003,
    "name": "Cornes"
  },
  {
    "level": 3,
    "code": 161004,
    "name": "Covas"
  },
  {
    "level": 3,
    "code": 161006,
    "name": "Gondarém"
  },
  {
    "level": 3,
    "code": 161007,
    "name": "Loivo"
  },
  {
    "level": 3,
    "code": 161009,
    "name": "Mentrestido"
  },
  {
    "level": 3,
    "code": 161012,
    "name": "Sapardos"
  },
  {
    "level": 3,
    "code": 161013,
    "name": "Sopo"
  },
  {
    "level": 3,
    "code": 161016,
    "name": "União das freguesias de Campos e Vila Meã"
  },
  {
    "level": 3,
    "code": 161017,
    "name": "União das freguesias de Candemil e Gondar"
  },
  {
    "level": 3,
    "code": 161018,
    "name": "União das freguesias de Reboreda e Nogueira"
  },
  {
    "level": 3,
    "code": 161019,
    "name": "União das freguesias de Vila Nova de Cerveira e Lovelhe"
  },
  {
    "level": 1,
    "code": 17,
    "name": "Vila Real"
  },
  {
    "level": 2,
    "code": 1701,
    "name": "Alijó"
  },
  {
    "level": 3,
    "code": 170101,
    "name": "Alijó"
  },
  {
    "level": 3,
    "code": 170107,
    "name": "Favaios"
  },
  {
    "level": 3,
    "code": 170108,
    "name": "Pegarinhos"
  },
  {
    "level": 3,
    "code": 170109,
    "name": "Pinhão"
  },
  {
    "level": 3,
    "code": 170112,
    "name": "Sanfins do Douro"
  },
  {
    "level": 3,
    "code": 170113,
    "name": "Santa Eugénia"
  },
  {
    "level": 3,
    "code": 170114,
    "name": "São Mamede de Ribatua"
  },
  {
    "level": 3,
    "code": 170116,
    "name": "Vila Chã"
  },
  {
    "level": 3,
    "code": 170117,
    "name": "Vila Verde"
  },
  {
    "level": 3,
    "code": 170118,
    "name": "Vilar de Maçada"
  },
  {
    "level": 3,
    "code": 170120,
    "name": "União das freguesias de Carlão e Amieiro"
  },
  {
    "level": 3,
    "code": 170121,
    "name": "União das freguesias de Castedo e Cotas"
  },
  {
    "level": 3,
    "code": 170122,
    "name": "União das freguesias de Pópulo e Ribalonga"
  },
  {
    "level": 3,
    "code": 170123,
    "name": "União das freguesias de Vale de Mendiz, Casal de Loivos e Vilarinho de Cotas"
  },
  {
    "level": 2,
    "code": 1702,
    "name": "Boticas"
  },
  {
    "level": 3,
    "code": 170203,
    "name": "Beça"
  },
  {
    "level": 3,
    "code": 170208,
    "name": "Covas do Barroso"
  },
  {
    "level": 3,
    "code": 170210,
    "name": "Dornelas"
  },
  {
    "level": 3,
    "code": 170213,
    "name": "Pinho"
  },
  {
    "level": 3,
    "code": 170215,
    "name": "Sapiãos"
  },
  {
    "level": 3,
    "code": 170217,
    "name": "Alturas do Barroso e Cerdedo"
  },
  {
    "level": 3,
    "code": 170218,
    "name": "Ardãos e Bobadela"
  },
  {
    "level": 3,
    "code": 170219,
    "name": "Boticas e Granja"
  },
  {
    "level": 3,
    "code": 170220,
    "name": "Codessoso, Curros e Fiães do Tâmega"
  },
  {
    "level": 3,
    "code": 170221,
    "name": "Vilar e Viveiro"
  },
  {
    "level": 2,
    "code": 1703,
    "name": "Chaves"
  },
  {
    "level": 3,
    "code": 170301,
    "name": "Águas Frias"
  },
  {
    "level": 3,
    "code": 170302,
    "name": "Anelhe"
  },
  {
    "level": 3,
    "code": 170305,
    "name": "Bustelo"
  },
  {
    "level": 3,
    "code": 170309,
    "name": "Cimo de Vila da Castanheira"
  },
  {
    "level": 3,
    "code": 170310,
    "name": "Curalha"
  },
  {
    "level": 3,
    "code": 170312,
    "name": "Ervededo"
  },
  {
    "level": 3,
    "code": 170313,
    "name": "Faiões"
  },
  {
    "level": 3,
    "code": 170314,
    "name": "Lama de Arcos"
  },
  {
    "level": 3,
    "code": 170316,
    "name": "Mairos"
  },
  {
    "level": 3,
    "code": 170317,
    "name": "Moreiras"
  },
  {
    "level": 3,
    "code": 170318,
    "name": "Nogueira da Montanha"
  },
  {
    "level": 3,
    "code": 170320,
    "name": "Oura"
  },
  {
    "level": 3,
    "code": 170321,
    "name": "Outeiro Seco"
  },
  {
    "level": 3,
    "code": 170322,
    "name": "Paradela"
  },
  {
    "level": 3,
    "code": 170324,
    "name": "Redondelo"
  },
  {
    "level": 3,
    "code": 170327,
    "name": "Sanfins"
  },
  {
    "level": 3,
    "code": 170329,
    "name": "Santa Leocádia"
  },
  {
    "level": 3,
    "code": 170330,
    "name": "Santo António de Monforte"
  },
  {
    "level": 3,
    "code": 170331,
    "name": "Santo Estêvão"
  },
  {
    "level": 3,
    "code": 170333,
    "name": "São Pedro de Agostém"
  },
  {
    "level": 3,
    "code": 170334,
    "name": "São Vicente"
  },
  {
    "level": 3,
    "code": 170340,
    "name": "Tronco"
  },
  {
    "level": 3,
    "code": 170341,
    "name": "Vale de Anta"
  },
  {
    "level": 3,
    "code": 170343,
    "name": "Vila Verde da Raia"
  },
  {
    "level": 3,
    "code": 170344,
    "name": "Vilar de Nantes"
  },
  {
    "level": 3,
    "code": 170345,
    "name": "Vilarelho da Raia"
  },
  {
    "level": 3,
    "code": 170347,
    "name": "Vilas Boas"
  },
  {
    "level": 3,
    "code": 170348,
    "name": "Vilela Seca"
  },
  {
    "level": 3,
    "code": 170349,
    "name": "Vilela do Tâmega"
  },
  {
    "level": 3,
    "code": 170350,
    "name": "Santa Maria Maior"
  },
  {
    "level": 3,
    "code": 170353,
    "name": "Planalto de Monforte (União das freguesias de Oucidres e Bobadela)"
  },
  {
    "level": 3,
    "code": 170354,
    "name": "União das freguesias da Madalena e Samaiões"
  },
  {
    "level": 3,
    "code": 170355,
    "name": "União das freguesias das Eiras, São Julião de Montenegro e Cela"
  },
  {
    "level": 3,
    "code": 170356,
    "name": "União das freguesias de Calvão e Soutelinho da Raia"
  },
  {
    "level": 3,
    "code": 170357,
    "name": "União das freguesias de Loivos e Póvoa de Agrações"
  },
  {
    "level": 3,
    "code": 170358,
    "name": "União das freguesias de Santa Cruz/Trindade e Sanjurge"
  },
  {
    "level": 3,
    "code": 170359,
    "name": "União das freguesias de Soutelo e Seara Velha"
  },
  {
    "level": 3,
    "code": 170360,
    "name": "União das freguesias de Travancas e Roriz"
  },
  {
    "level": 3,
    "code": 170361,
    "name": "Vidago (União das freguesias de Vidago, Arcossó, Selhariz e Vilarinho das Paranheiras)"
  },
  {
    "level": 2,
    "code": 1704,
    "name": "Mesão Frio"
  },
  {
    "level": 3,
    "code": 170401,
    "name": "Barqueiros"
  },
  {
    "level": 3,
    "code": 170402,
    "name": "Cidadelhe"
  },
  {
    "level": 3,
    "code": 170403,
    "name": "Oliveira"
  },
  {
    "level": 3,
    "code": 170407,
    "name": "Vila Marim"
  },
  {
    "level": 3,
    "code": 170408,
    "name": "Mesão Frio (Santo André)"
  },
  {
    "level": 2,
    "code": 1705,
    "name": "Mondim de Basto"
  },
  {
    "level": 3,
    "code": 170501,
    "name": "Atei"
  },
  {
    "level": 3,
    "code": 170502,
    "name": "Bilhó"
  },
  {
    "level": 3,
    "code": 170505,
    "name": "São Cristóvão de Mondim de Basto"
  },
  {
    "level": 3,
    "code": 170508,
    "name": "Vilar de Ferreiros"
  },
  {
    "level": 3,
    "code": 170509,
    "name": "União das freguesias de Campanhó e Paradança"
  },
  {
    "level": 3,
    "code": 170510,
    "name": "União das freguesias de Ermelo e Pardelhas"
  },
  {
    "level": 2,
    "code": 1706,
    "name": "Montalegre"
  },
  {
    "level": 3,
    "code": 170601,
    "name": "Cabril"
  },
  {
    "level": 3,
    "code": 170603,
    "name": "Cervos"
  },
  {
    "level": 3,
    "code": 170604,
    "name": "Chã"
  },
  {
    "level": 3,
    "code": 170607,
    "name": "Covelo do Gerês"
  },
  {
    "level": 3,
    "code": 170609,
    "name": "Ferral"
  },
  {
    "level": 3,
    "code": 170612,
    "name": "Gralhas"
  },
  {
    "level": 3,
    "code": 170616,
    "name": "Morgade"
  },
  {
    "level": 3,
    "code": 170618,
    "name": "Negrões"
  },
  {
    "level": 3,
    "code": 170619,
    "name": "Outeiro"
  },
  {
    "level": 3,
    "code": 170623,
    "name": "Pitões das Junias"
  },
  {
    "level": 3,
    "code": 170625,
    "name": "Reigoso"
  },
  {
    "level": 3,
    "code": 170626,
    "name": "Salto"
  },
  {
    "level": 3,
    "code": 170627,
    "name": "Santo André"
  },
  {
    "level": 3,
    "code": 170629,
    "name": "Sarraquinhos"
  },
  {
    "level": 3,
    "code": 170631,
    "name": "Solveira"
  },
  {
    "level": 3,
    "code": 170632,
    "name": "Tourém"
  },
  {
    "level": 3,
    "code": 170635,
    "name": "Vila da Ponte"
  },
  {
    "level": 3,
    "code": 170636,
    "name": "União das freguesias de Cambeses do Rio, Donões e Mourilhe"
  },
  {
    "level": 3,
    "code": 170637,
    "name": "União das freguesias de Meixedo e Padornelos"
  },
  {
    "level": 3,
    "code": 170638,
    "name": "União das freguesias de Montalegre e Padroso"
  },
  {
    "level": 3,
    "code": 170639,
    "name": "União das freguesias de Paradela, Contim e Fiães"
  },
  {
    "level": 3,
    "code": 170640,
    "name": "União das freguesias de Sezelhe e Covelães"
  },
  {
    "level": 3,
    "code": 170641,
    "name": "União das freguesias de Venda Nova e Pondras"
  },
  {
    "level": 3,
    "code": 170642,
    "name": "União das freguesias de Viade de Baixo e Fervidelas"
  },
  {
    "level": 3,
    "code": 170643,
    "name": "União das freguesias de Vilar de Perdizes e Meixide"
  },
  {
    "level": 2,
    "code": 1707,
    "name": "Murça"
  },
  {
    "level": 3,
    "code": 170701,
    "name": "Candedo"
  },
  {
    "level": 3,
    "code": 170703,
    "name": "Fiolhoso"
  },
  {
    "level": 3,
    "code": 170704,
    "name": "Jou"
  },
  {
    "level": 3,
    "code": 170705,
    "name": "Murça"
  },
  {
    "level": 3,
    "code": 170708,
    "name": "Valongo de Milhais"
  },
  {
    "level": 3,
    "code": 170710,
    "name": "União das freguesias de Carva e Vilares"
  },
  {
    "level": 3,
    "code": 170711,
    "name": "União das freguesias de Noura e Palheiros"
  },
  {
    "level": 2,
    "code": 1708,
    "name": "Peso da Régua"
  },
  {
    "level": 3,
    "code": 170802,
    "name": "Fontelas"
  },
  {
    "level": 3,
    "code": 170805,
    "name": "Loureiro"
  },
  {
    "level": 3,
    "code": 170809,
    "name": "Sedielos"
  },
  {
    "level": 3,
    "code": 170810,
    "name": "Vilarinho dos Freires"
  },
  {
    "level": 3,
    "code": 170813,
    "name": "União das freguesias de Galafura e Covelinhas"
  },
  {
    "level": 3,
    "code": 170814,
    "name": "União das freguesias de Moura Morta e Vinhós"
  },
  {
    "level": 3,
    "code": 170815,
    "name": "União das freguesias de Peso da Régua e Godim"
  },
  {
    "level": 3,
    "code": 170816,
    "name": "União das freguesias de Poiares e Canelas"
  },
  {
    "level": 2,
    "code": 1709,
    "name": "Ribeira de Pena"
  },
  {
    "level": 3,
    "code": 170901,
    "name": "Alvadia"
  },
  {
    "level": 3,
    "code": 170902,
    "name": "Canedo"
  },
  {
    "level": 3,
    "code": 170906,
    "name": "Santa Marinha"
  },
  {
    "level": 3,
    "code": 170908,
    "name": "União das freguesias de Cerva e Limões"
  },
  {
    "level": 3,
    "code": 170909,
    "name": "União das freguesias de Ribeira de Pena (Salvador) e Santo Aleixo de Além-Tâmega"
  },
  {
    "level": 2,
    "code": 1710,
    "name": "Sabrosa"
  },
  {
    "level": 3,
    "code": 171001,
    "name": "Celeirós"
  },
  {
    "level": 3,
    "code": 171002,
    "name": "Covas do Douro"
  },
  {
    "level": 3,
    "code": 171004,
    "name": "Gouvinhas"
  },
  {
    "level": 3,
    "code": 171005,
    "name": "Parada de Pinhão"
  },
  {
    "level": 3,
    "code": 171007,
    "name": "Paços"
  },
  {
    "level": 3,
    "code": 171009,
    "name": "Sabrosa"
  },
  {
    "level": 3,
    "code": 171011,
    "name": "São Lourenço de Ribapinhão"
  },
  {
    "level": 3,
    "code": 171013,
    "name": "Souto Maior"
  },
  {
    "level": 3,
    "code": 171014,
    "name": "Torre do Pinhão"
  },
  {
    "level": 3,
    "code": 171015,
    "name": "Vilarinho de São Romão"
  },
  {
    "level": 3,
    "code": 171016,
    "name": "União das freguesias de Provesende, Gouvães do Douro e São Cristóvão do Douro"
  },
  {
    "level": 3,
    "code": 171017,
    "name": "União das freguesias de São Martinho de Antas e Paradela de Guiães"
  },
  {
    "level": 2,
    "code": 1711,
    "name": "Santa Marta de Penaguião"
  },
  {
    "level": 3,
    "code": 171101,
    "name": "Alvações do Corgo"
  },
  {
    "level": 3,
    "code": 171102,
    "name": "Cumieira"
  },
  {
    "level": 3,
    "code": 171103,
    "name": "Fontes"
  },
  {
    "level": 3,
    "code": 171106,
    "name": "Medrões"
  },
  {
    "level": 3,
    "code": 171110,
    "name": "Sever"
  },
  {
    "level": 3,
    "code": 171111,
    "name": "União das freguesias de Lobrigos (São Miguel e São João Baptista) e Sanhoane"
  },
  {
    "level": 3,
    "code": 171112,
    "name": "União das freguesias de Louredo e Fornelos"
  },
  {
    "level": 2,
    "code": 1712,
    "name": "Valpaços"
  },
  {
    "level": 3,
    "code": 171201,
    "name": "Água Revés e Crasto"
  },
  {
    "level": 3,
    "code": 171203,
    "name": "Algeriz"
  },
  {
    "level": 3,
    "code": 171205,
    "name": "Bouçoães"
  },
  {
    "level": 3,
    "code": 171206,
    "name": "Canaveses"
  },
  {
    "level": 3,
    "code": 171209,
    "name": "Ervões"
  },
  {
    "level": 3,
    "code": 171211,
    "name": "Fornos do Pinhal"
  },
  {
    "level": 3,
    "code": 171212,
    "name": "Friões"
  },
  {
    "level": 3,
    "code": 171215,
    "name": "Padrela e Tazem"
  },
  {
    "level": 3,
    "code": 171216,
    "name": "Possacos"
  },
  {
    "level": 3,
    "code": 171217,
    "name": "Rio Torto"
  },
  {
    "level": 3,
    "code": 171219,
    "name": "Santa Maria de Emeres"
  },
  {
    "level": 3,
    "code": 171220,
    "name": "Santa Valha"
  },
  {
    "level": 3,
    "code": 171221,
    "name": "Santiago da Ribeira de Alhariz"
  },
  {
    "level": 3,
    "code": 171222,
    "name": "São João da Corveira"
  },
  {
    "level": 3,
    "code": 171223,
    "name": "São Pedro de Veiga de Lila"
  },
  {
    "level": 3,
    "code": 171224,
    "name": "Serapicos"
  },
  {
    "level": 3,
    "code": 171227,
    "name": "Vales"
  },
  {
    "level": 3,
    "code": 171229,
    "name": "Vassal"
  },
  {
    "level": 3,
    "code": 171230,
    "name": "Veiga de Lila"
  },
  {
    "level": 3,
    "code": 171231,
    "name": "Vilarandelo"
  },
  {
    "level": 3,
    "code": 171232,
    "name": "Carrazedo de Montenegro e Curros"
  },
  {
    "level": 3,
    "code": 171233,
    "name": "Lebução, Fiães e Nozelos"
  },
  {
    "level": 3,
    "code": 171234,
    "name": "Sonim e Barreiros"
  },
  {
    "level": 3,
    "code": 171235,
    "name": "Tinhela e Alvarelhos"
  },
  {
    "level": 3,
    "code": 171236,
    "name": "Valpaços e Sanfins"
  },
  {
    "level": 2,
    "code": 1713,
    "name": "Vila Pouca de Aguiar"
  },
  {
    "level": 3,
    "code": 171302,
    "name": "Alfarela de Jales"
  },
  {
    "level": 3,
    "code": 171303,
    "name": "Bornes de Aguiar"
  },
  {
    "level": 3,
    "code": 171304,
    "name": "Bragado"
  },
  {
    "level": 3,
    "code": 171305,
    "name": "Capeludos"
  },
  {
    "level": 3,
    "code": 171310,
    "name": "Soutelo de Aguiar"
  },
  {
    "level": 3,
    "code": 171311,
    "name": "Telões"
  },
  {
    "level": 3,
    "code": 171312,
    "name": "Tresminas"
  },
  {
    "level": 3,
    "code": 171313,
    "name": "Valoura"
  },
  {
    "level": 3,
    "code": 171314,
    "name": "Vila Pouca de Aguiar"
  },
  {
    "level": 3,
    "code": 171315,
    "name": "Vreia de Bornes"
  },
  {
    "level": 3,
    "code": 171316,
    "name": "Vreia de Jales"
  },
  {
    "level": 3,
    "code": 171317,
    "name": "Sabroso de Aguiar"
  },
  {
    "level": 3,
    "code": 171319,
    "name": "Alvão"
  },
  {
    "level": 3,
    "code": 171320,
    "name": "União das freguesias de Pensalvos e Parada de Monteiros"
  },
  {
    "level": 2,
    "code": 1714,
    "name": "Vila Real"
  },
  {
    "level": 3,
    "code": 171401,
    "name": "Abaças"
  },
  {
    "level": 3,
    "code": 171403,
    "name": "Andrães"
  },
  {
    "level": 3,
    "code": 171404,
    "name": "Arroios"
  },
  {
    "level": 3,
    "code": 171406,
    "name": "Campeã"
  },
  {
    "level": 3,
    "code": 171409,
    "name": "Folhadela"
  },
  {
    "level": 3,
    "code": 171410,
    "name": "Guiães"
  },
  {
    "level": 3,
    "code": 171414,
    "name": "Lordelo"
  },
  {
    "level": 3,
    "code": 171415,
    "name": "Mateus"
  },
  {
    "level": 3,
    "code": 171416,
    "name": "Mondrões"
  },
  {
    "level": 3,
    "code": 171420,
    "name": "Parada de Cunhos"
  },
  {
    "level": 3,
    "code": 171426,
    "name": "Torgueda"
  },
  {
    "level": 3,
    "code": 171429,
    "name": "Vila Marim"
  },
  {
    "level": 3,
    "code": 171431,
    "name": "União das freguesias de Adoufe e Vilarinho de Samardã"
  },
  {
    "level": 3,
    "code": 171432,
    "name": "União das freguesias de Borbela e Lamas de Olo"
  },
  {
    "level": 3,
    "code": 171433,
    "name": "União das freguesias de Constantim e Vale de Nogueiras"
  },
  {
    "level": 3,
    "code": 171434,
    "name": "União das freguesias de Mouçós e Lamares"
  },
  {
    "level": 3,
    "code": 171435,
    "name": "União das freguesias de Nogueira e Ermida"
  },
  {
    "level": 3,
    "code": 171436,
    "name": "União das freguesias de Pena, Quintã e Vila Cova"
  },
  {
    "level": 3,
    "code": 171437,
    "name": "União das freguesias de São Tomé do Castelo e Justes"
  },
  {
    "level": 3,
    "code": 171438,
    "name": "Vila Real"
  },
  {
    "level": 1,
    "code": 18,
    "name": "Viseu"
  },
  {
    "level": 2,
    "code": 1801,
    "name": "Armamar"
  },
  {
    "level": 3,
    "code": 180101,
    "name": "Aldeias"
  },
  {
    "level": 3,
    "code": 180104,
    "name": "Cimbres"
  },
  {
    "level": 3,
    "code": 180106,
    "name": "Folgosa"
  },
  {
    "level": 3,
    "code": 180107,
    "name": "Fontelo"
  },
  {
    "level": 3,
    "code": 180109,
    "name": "Queimada"
  },
  {
    "level": 3,
    "code": 180110,
    "name": "Queimadela"
  },
  {
    "level": 3,
    "code": 180111,
    "name": "Santa Cruz"
  },
  {
    "level": 3,
    "code": 180114,
    "name": "São Cosmado"
  },
  {
    "level": 3,
    "code": 180115,
    "name": "São Martinho das Chãs"
  },
  {
    "level": 3,
    "code": 180118,
    "name": "Vacalar"
  },
  {
    "level": 3,
    "code": 180120,
    "name": "Armamar"
  },
  {
    "level": 3,
    "code": 180121,
    "name": "União das freguesias de Aricera e Goujoim"
  },
  {
    "level": 3,
    "code": 180122,
    "name": "União das freguesias de São Romão e Santiago"
  },
  {
    "level": 3,
    "code": 180123,
    "name": "União das freguesias de Vila Seca e Santo Adrião"
  },
  {
    "level": 2,
    "code": 1802,
    "name": "Carregal do Sal"
  },
  {
    "level": 3,
    "code": 180201,
    "name": "Beijós"
  },
  {
    "level": 3,
    "code": 180202,
    "name": "Cabanas de Viriato"
  },
  {
    "level": 3,
    "code": 180204,
    "name": "Oliveira do Conde"
  },
  {
    "level": 3,
    "code": 180206,
    "name": "Parada"
  },
  {
    "level": 3,
    "code": 180208,
    "name": "Carregal do Sal"
  },
  {
    "level": 2,
    "code": 1803,
    "name": "Castro Daire"
  },
  {
    "level": 3,
    "code": 180301,
    "name": "Almofala"
  },
  {
    "level": 3,
    "code": 180303,
    "name": "Cabril"
  },
  {
    "level": 3,
    "code": 180304,
    "name": "Castro Daire"
  },
  {
    "level": 3,
    "code": 180305,
    "name": "Cujó"
  },
  {
    "level": 3,
    "code": 180309,
    "name": "Gosende"
  },
  {
    "level": 3,
    "code": 180312,
    "name": "Mões"
  },
  {
    "level": 3,
    "code": 180313,
    "name": "Moledo"
  },
  {
    "level": 3,
    "code": 180314,
    "name": "Monteiras"
  },
  {
    "level": 3,
    "code": 180317,
    "name": "Pepim"
  },
  {
    "level": 3,
    "code": 180319,
    "name": "Pinheiro"
  },
  {
    "level": 3,
    "code": 180322,
    "name": "São Joaninho"
  },
  {
    "level": 3,
    "code": 180323,
    "name": "União das freguesias de Mamouros, Alva e Ribolhos"
  },
  {
    "level": 3,
    "code": 180324,
    "name": "União das freguesias de Mezio e Moura Morta"
  },
  {
    "level": 3,
    "code": 180325,
    "name": "União das freguesias de Parada de Ester e Ester"
  },
  {
    "level": 3,
    "code": 180326,
    "name": "União das freguesias de Picão e Ermida"
  },
  {
    "level": 3,
    "code": 180327,
    "name": "União das freguesias de Reriz e Gafanhão"
  },
  {
    "level": 2,
    "code": 1804,
    "name": "Cinfães"
  },
  {
    "level": 3,
    "code": 180403,
    "name": "Cinfães"
  },
  {
    "level": 3,
    "code": 180404,
    "name": "Espadanedo"
  },
  {
    "level": 3,
    "code": 180405,
    "name": "Ferreiros de Tendais"
  },
  {
    "level": 3,
    "code": 180406,
    "name": "Fornelos"
  },
  {
    "level": 3,
    "code": 180408,
    "name": "Moimenta"
  },
  {
    "level": 3,
    "code": 180409,
    "name": "Nespereira"
  },
  {
    "level": 3,
    "code": 180410,
    "name": "Oliveira do Douro"
  },
  {
    "level": 3,
    "code": 180412,
    "name": "Santiago de Piães"
  },
  {
    "level": 3,
    "code": 180413,
    "name": "São Cristóvão de Nogueira"
  },
  {
    "level": 3,
    "code": 180414,
    "name": "Souselo"
  },
  {
    "level": 3,
    "code": 180415,
    "name": "Tarouquela"
  },
  {
    "level": 3,
    "code": 180416,
    "name": "Tendais"
  },
  {
    "level": 3,
    "code": 180417,
    "name": "Travanca"
  },
  {
    "level": 3,
    "code": 180418,
    "name": "União das freguesias de Alhões, Bustelo, Gralheira e Ramires"
  },
  {
    "level": 2,
    "code": 1805,
    "name": "Lamego"
  },
  {
    "level": 3,
    "code": 180502,
    "name": "Avões"
  },
  {
    "level": 3,
    "code": 180504,
    "name": "Britiande"
  },
  {
    "level": 3,
    "code": 180505,
    "name": "Cambres"
  },
  {
    "level": 3,
    "code": 180507,
    "name": "Ferreirim"
  },
  {
    "level": 3,
    "code": 180508,
    "name": "Ferreiros de Avões"
  },
  {
    "level": 3,
    "code": 180509,
    "name": "Figueira"
  },
  {
    "level": 3,
    "code": 180510,
    "name": "Lalim"
  },
  {
    "level": 3,
    "code": 180511,
    "name": "Lazarim"
  },
  {
    "level": 3,
    "code": 180516,
    "name": "Penajóia"
  },
  {
    "level": 3,
    "code": 180517,
    "name": "Penude"
  },
  {
    "level": 3,
    "code": 180519,
    "name": "Samodães"
  },
  {
    "level": 3,
    "code": 180520,
    "name": "Sande"
  },
  {
    "level": 3,
    "code": 180523,
    "name": "Várzea de Abrunhais"
  },
  {
    "level": 3,
    "code": 180524,
    "name": "Vila Nova de Souto d\'El-Rei"
  },
  {
    "level": 3,
    "code": 180525,
    "name": "Lamego (Almacave e Sé)"
  },
  {
    "level": 3,
    "code": 180526,
    "name": "União das freguesias de Bigorne, Magueija e Pretarouca"
  },
  {
    "level": 3,
    "code": 180527,
    "name": "União das freguesias de Cepões, Meijinhos e Melcões"
  },
  {
    "level": 3,
    "code": 180528,
    "name": "União das freguesias de Parada do Bispo e Valdigem"
  },
  {
    "level": 2,
    "code": 1806,
    "name": "Mangualde"
  },
  {
    "level": 3,
    "code": 180601,
    "name": "Abrunhosa-a-Velha"
  },
  {
    "level": 3,
    "code": 180602,
    "name": "Alcafache"
  },
  {
    "level": 3,
    "code": 180605,
    "name": "Cunha Baixa"
  },
  {
    "level": 3,
    "code": 180606,
    "name": "Espinho"
  },
  {
    "level": 3,
    "code": 180607,
    "name": "Fornos de Maceira Dão"
  },
  {
    "level": 3,
    "code": 180608,
    "name": "Freixiosa"
  },
  {
    "level": 3,
    "code": 180614,
    "name": "Quintela de Azurara"
  },
  {
    "level": 3,
    "code": 180616,
    "name": "São João da Fresta"
  },
  {
    "level": 3,
    "code": 180619,
    "name": "União das freguesias de Mangualde, Mesquitela e Cunha Alta"
  },
  {
    "level": 3,
    "code": 180620,
    "name": "União das freguesias de Moimenta de Maceira Dão e Lobelhe do Mato"
  },
  {
    "level": 3,
    "code": 180621,
    "name": "União das freguesias de Santiago de Cassurrães e Póvoa de Cervães"
  },
  {
    "level": 3,
    "code": 180622,
    "name": "União das freguesias de Tavares (Chãs, Várzea e Travanca)"
  },
  {
    "level": 2,
    "code": 1807,
    "name": "Moimenta da Beira"
  },
  {
    "level": 3,
    "code": 180702,
    "name": "Alvite"
  },
  {
    "level": 3,
    "code": 180703,
    "name": "Arcozelos"
  },
  {
    "level": 3,
    "code": 180705,
    "name": "Baldos"
  },
  {
    "level": 3,
    "code": 180706,
    "name": "Cabaços"
  },
  {
    "level": 3,
    "code": 180707,
    "name": "Caria"
  },
  {
    "level": 3,
    "code": 180708,
    "name": "Castelo"
  },
  {
    "level": 3,
    "code": 180709,
    "name": "Leomil"
  },
  {
    "level": 3,
    "code": 180710,
    "name": "Moimenta da Beira"
  },
  {
    "level": 3,
    "code": 180713,
    "name": "Passô"
  },
  {
    "level": 3,
    "code": 180716,
    "name": "Rua"
  },
  {
    "level": 3,
    "code": 180717,
    "name": "Sarzedo"
  },
  {
    "level": 3,
    "code": 180719,
    "name": "Sever"
  },
  {
    "level": 3,
    "code": 180720,
    "name": "Vilar"
  },
  {
    "level": 3,
    "code": 180721,
    "name": "União das freguesias de Paradinha e Nagosa"
  },
  {
    "level": 3,
    "code": 180722,
    "name": "União das freguesias de Pêra Velha, Aldeia de Nacomba e Ariz"
  },
  {
    "level": 3,
    "code": 180723,
    "name": "União das freguesias de Peva e Segões"
  },
  {
    "level": 2,
    "code": 1808,
    "name": "Mortágua"
  },
  {
    "level": 3,
    "code": 180802,
    "name": "Cercosa"
  },
  {
    "level": 3,
    "code": 180804,
    "name": "Espinho"
  },
  {
    "level": 3,
    "code": 180805,
    "name": "Marmeleira"
  },
  {
    "level": 3,
    "code": 180807,
    "name": "Pala"
  },
  {
    "level": 3,
    "code": 180808,
    "name": "Sobral"
  },
  {
    "level": 3,
    "code": 180809,
    "name": "Trezói"
  },
  {
    "level": 3,
    "code": 180811,
    "name": "União das freguesias de Mortágua, Vale de Remígio, Cortegaça e Almaça"
  },
  {
    "level": 2,
    "code": 1809,
    "name": "Nelas"
  },
  {
    "level": 3,
    "code": 180901,
    "name": "Canas de Senhorim"
  },
  {
    "level": 3,
    "code": 180903,
    "name": "Nelas"
  },
  {
    "level": 3,
    "code": 180905,
    "name": "Senhorim"
  },
  {
    "level": 3,
    "code": 180906,
    "name": "Vilar Seco"
  },
  {
    "level": 3,
    "code": 180908,
    "name": "Lapa do Lobo"
  },
  {
    "level": 3,
    "code": 180910,
    "name": "União das freguesias de Carvalhal Redondo e Aguieira"
  },
  {
    "level": 3,
    "code": 180911,
    "name": "União das freguesias de Santar e Moreira"
  },
  {
    "level": 2,
    "code": 1810,
    "name": "Oliveira de Frades"
  },
  {
    "level": 3,
    "code": 181002,
    "name": "Arcozelo das Maias"
  },
  {
    "level": 3,
    "code": 181005,
    "name": "Pinheiro"
  },
  {
    "level": 3,
    "code": 181007,
    "name": "Ribeiradio"
  },
  {
    "level": 3,
    "code": 181008,
    "name": "São João da Serra"
  },
  {
    "level": 3,
    "code": 181009,
    "name": "São Vicente de Lafões"
  },
  {
    "level": 3,
    "code": 181013,
    "name": "União das freguesias de Arca e Varzielas"
  },
  {
    "level": 3,
    "code": 181014,
    "name": "União das freguesias de Destriz e Reigoso"
  },
  {
    "level": 3,
    "code": 181015,
    "name": "União das freguesias de Oliveira de Frades, Souto de Lafões e Sejães"
  },
  {
    "level": 2,
    "code": 1811,
    "name": "Penalva do Castelo"
  },
  {
    "level": 3,
    "code": 181102,
    "name": "Castelo de Penalva"
  },
  {
    "level": 3,
    "code": 181103,
    "name": "Esmolfe"
  },
  {
    "level": 3,
    "code": 181104,
    "name": "Germil"
  },
  {
    "level": 3,
    "code": 181105,
    "name": "Ínsua"
  },
  {
    "level": 3,
    "code": 181106,
    "name": "Lusinde"
  },
  {
    "level": 3,
    "code": 181109,
    "name": "Pindo"
  },
  {
    "level": 3,
    "code": 181110,
    "name": "Real"
  },
  {
    "level": 3,
    "code": 181111,
    "name": "Sezures"
  },
  {
    "level": 3,
    "code": 181112,
    "name": "Trancozelos"
  },
  {
    "level": 3,
    "code": 181114,
    "name": "União das freguesias de Antas e Matela"
  },
  {
    "level": 3,
    "code": 181115,
    "name": "União das freguesias de Vila Cova do Covelo/Mareco"
  },
  {
    "level": 2,
    "code": 1812,
    "name": "Penedono"
  },
  {
    "level": 3,
    "code": 181202,
    "name": "Beselga"
  },
  {
    "level": 3,
    "code": 181203,
    "name": "Castainço"
  },
  {
    "level": 3,
    "code": 181207,
    "name": "Penela da Beira"
  },
  {
    "level": 3,
    "code": 181208,
    "name": "Póvoa de Penela"
  },
  {
    "level": 3,
    "code": 181209,
    "name": "Souto"
  },
  {
    "level": 3,
    "code": 181210,
    "name": "União das freguesias de Antas e Ourozinho"
  },
  {
    "level": 3,
    "code": 181211,
    "name": "União das freguesias de Penedono e Granja"
  },
  {
    "level": 2,
    "code": 1813,
    "name": "Resende"
  },
  {
    "level": 3,
    "code": 181302,
    "name": "Barrô"
  },
  {
    "level": 3,
    "code": 181303,
    "name": "Cárquere"
  },
  {
    "level": 3,
    "code": 181310,
    "name": "Paus"
  },
  {
    "level": 3,
    "code": 181311,
    "name": "Resende"
  },
  {
    "level": 3,
    "code": 181312,
    "name": "São Cipriano"
  },
  {
    "level": 3,
    "code": 181313,
    "name": "São João de Fontoura"
  },
  {
    "level": 3,
    "code": 181314,
    "name": "São Martinho de Mouros"
  },
  {
    "level": 3,
    "code": 181316,
    "name": "União das freguesias de Anreade e São Romão de Aregos"
  },
  {
    "level": 3,
    "code": 181317,
    "name": "União das freguesias de Felgueiras e Feirão"
  },
  {
    "level": 3,
    "code": 181318,
    "name": "União das freguesias de Freigil e Miomães"
  },
  {
    "level": 3,
    "code": 181319,
    "name": "União das freguesias de Ovadas e Panchorra"
  },
  {
    "level": 2,
    "code": 1814,
    "name": "Santa Comba Dão"
  },
  {
    "level": 3,
    "code": 181403,
    "name": "Pinheiro de Ázere"
  },
  {
    "level": 3,
    "code": 181405,
    "name": "São Joaninho"
  },
  {
    "level": 3,
    "code": 181406,
    "name": "São João de Areias"
  },
  {
    "level": 3,
    "code": 181410,
    "name": "União das freguesias de Ovoa e Vimieiro"
  },
  {
    "level": 3,
    "code": 181411,
    "name": "União das freguesias de Santa Comba Dão e Couto do Mosteiro"
  },
  {
    "level": 3,
    "code": 181412,
    "name": "União das freguesias de Treixedo e Nagozela"
  },
  {
    "level": 2,
    "code": 1815,
    "name": "São João da Pesqueira"
  },
  {
    "level": 3,
    "code": 181501,
    "name": "Castanheiro do Sul"
  },
  {
    "level": 3,
    "code": 181502,
    "name": "Ervedosa do Douro"
  },
  {
    "level": 3,
    "code": 181504,
    "name": "Nagozelo do Douro"
  },
  {
    "level": 3,
    "code": 181505,
    "name": "Paredes da Beira"
  },
  {
    "level": 3,
    "code": 181507,
    "name": "Riodades"
  },
  {
    "level": 3,
    "code": 181509,
    "name": "Soutelo do Douro"
  },
  {
    "level": 3,
    "code": 181511,
    "name": "Vale de Figueira"
  },
  {
    "level": 3,
    "code": 181512,
    "name": "Valongo dos Azeites"
  },
  {
    "level": 3,
    "code": 181515,
    "name": "União das freguesias de São João da Pesqueira e Várzea de Trevões"
  },
  {
    "level": 3,
    "code": 181516,
    "name": "União das freguesias de Trevões e Espinhosa"
  },
  {
    "level": 3,
    "code": 181517,
    "name": "União das freguesias de Vilarouco e Pereiros"
  },
  {
    "level": 2,
    "code": 1816,
    "name": "São Pedro do Sul"
  },
  {
    "level": 3,
    "code": 181602,
    "name": "Bordonhos"
  },
  {
    "level": 3,
    "code": 181606,
    "name": "Figueiredo de Alva"
  },
  {
    "level": 3,
    "code": 181607,
    "name": "Manhouce"
  },
  {
    "level": 3,
    "code": 181608,
    "name": "Pindelo dos Milagres"
  },
  {
    "level": 3,
    "code": 181609,
    "name": "Pinho"
  },
  {
    "level": 3,
    "code": 181612,
    "name": "São Félix"
  },
  {
    "level": 3,
    "code": 181615,
    "name": "Serrazes"
  },
  {
    "level": 3,
    "code": 181616,
    "name": "Sul"
  },
  {
    "level": 3,
    "code": 181617,
    "name": "Valadares"
  },
  {
    "level": 3,
    "code": 181619,
    "name": "Vila Maior"
  },
  {
    "level": 3,
    "code": 181620,
    "name": "União das freguesias de Carvalhais e Candal"
  },
  {
    "level": 3,
    "code": 181621,
    "name": "União das freguesias de Santa Cruz da Trapa e São Cristóvão de Lafões"
  },
  {
    "level": 3,
    "code": 181622,
    "name": "União das freguesias de São Martinho das Moitas e Covas do Rio"
  },
  {
    "level": 3,
    "code": 181623,
    "name": "União das freguesias de São Pedro do Sul, Várzea e Baiões"
  },
  {
    "level": 2,
    "code": 1817,
    "name": "Sátão"
  },
  {
    "level": 3,
    "code": 181702,
    "name": "Avelal"
  },
  {
    "level": 3,
    "code": 181704,
    "name": "Ferreira de Aves"
  },
  {
    "level": 3,
    "code": 181706,
    "name": "Mioma"
  },
  {
    "level": 3,
    "code": 181707,
    "name": "Rio de Moinhos"
  },
  {
    "level": 3,
    "code": 181709,
    "name": "São Miguel de Vila Boa"
  },
  {
    "level": 3,
    "code": 181710,
    "name": "Sátão"
  },
  {
    "level": 3,
    "code": 181711,
    "name": "Silvã de Cima"
  },
  {
    "level": 3,
    "code": 181713,
    "name": "União das freguesias de Águas Boas e Forles"
  },
  {
    "level": 3,
    "code": 181714,
    "name": "União das freguesias de Romãs, Decermilo e Vila Longa"
  },
  {
    "level": 2,
    "code": 1818,
    "name": "Sernancelhe"
  },
  {
    "level": 3,
    "code": 181801,
    "name": "Arnas"
  },
  {
    "level": 3,
    "code": 181802,
    "name": "Carregal"
  },
  {
    "level": 3,
    "code": 181803,
    "name": "Chosendo"
  },
  {
    "level": 3,
    "code": 181804,
    "name": "Cunha"
  },
  {
    "level": 3,
    "code": 181806,
    "name": "Faia"
  },
  {
    "level": 3,
    "code": 181810,
    "name": "Granjal"
  },
  {
    "level": 3,
    "code": 181811,
    "name": "Lamosa"
  },
  {
    "level": 3,
    "code": 181814,
    "name": "Quintela"
  },
  {
    "level": 3,
    "code": 181817,
    "name": "Vila da Ponte"
  },
  {
    "level": 3,
    "code": 181818,
    "name": "União das freguesias de Ferreirim e Macieira"
  },
  {
    "level": 3,
    "code": 181819,
    "name": "União das freguesias de Fonte Arcada e Escurquela"
  },
  {
    "level": 3,
    "code": 181820,
    "name": "União das freguesias de Penso e Freixinho"
  },
  {
    "level": 3,
    "code": 181821,
    "name": "União das freguesias de Sernancelhe e Sarzeda"
  },
  {
    "level": 2,
    "code": 1819,
    "name": "Tabuaço"
  },
  {
    "level": 3,
    "code": 181901,
    "name": "Adorigo"
  },
  {
    "level": 3,
    "code": 181902,
    "name": "Arcos"
  },
  {
    "level": 3,
    "code": 181904,
    "name": "Chavães"
  },
  {
    "level": 3,
    "code": 181905,
    "name": "Desejosa"
  },
  {
    "level": 3,
    "code": 181906,
    "name": "Granja do Tedo"
  },
  {
    "level": 3,
    "code": 181908,
    "name": "Longa"
  },
  {
    "level": 3,
    "code": 181913,
    "name": "Sendim"
  },
  {
    "level": 3,
    "code": 181914,
    "name": "Tabuaço"
  },
  {
    "level": 3,
    "code": 181917,
    "name": "Valença do Douro"
  },
  {
    "level": 3,
    "code": 181918,
    "name": "União das freguesias de Barcos e Santa Leocádia"
  },
  {
    "level": 3,
    "code": 181919,
    "name": "União das freguesias de Paradela e Granjinha"
  },
  {
    "level": 3,
    "code": 181920,
    "name": "União das freguesias de Pinheiros e Vale de Figueira"
  },
  {
    "level": 3,
    "code": 181921,
    "name": "União das freguesias de Távora e Pereiro"
  },
  {
    "level": 2,
    "code": 1820,
    "name": "Tarouca"
  },
  {
    "level": 3,
    "code": 182004,
    "name": "Mondim da Beira"
  },
  {
    "level": 3,
    "code": 182005,
    "name": "Salzedas"
  },
  {
    "level": 3,
    "code": 182006,
    "name": "São João de Tarouca"
  },
  {
    "level": 3,
    "code": 182009,
    "name": "Várzea da Serra"
  },
  {
    "level": 3,
    "code": 182011,
    "name": "União das freguesias de Gouviães e Ucanha"
  },
  {
    "level": 3,
    "code": 182012,
    "name": "União das freguesias de Granja Nova e Vila Chã da Beira"
  },
  {
    "level": 3,
    "code": 182013,
    "name": "União das freguesias de Tarouca e Dálvares"
  },
  {
    "level": 2,
    "code": 1821,
    "name": "Tondela"
  },
  {
    "level": 3,
    "code": 182102,
    "name": "Campo de Besteiros"
  },
  {
    "level": 3,
    "code": 182103,
    "name": "Canas de Santa Maria"
  },
  {
    "level": 3,
    "code": 182105,
    "name": "Castelões"
  },
  {
    "level": 3,
    "code": 182106,
    "name": "Dardavaz"
  },
  {
    "level": 3,
    "code": 182107,
    "name": "Ferreirós do Dão"
  },
  {
    "level": 3,
    "code": 182108,
    "name": "Guardão"
  },
  {
    "level": 3,
    "code": 182109,
    "name": "Lajeosa do Dão"
  },
  {
    "level": 3,
    "code": 182110,
    "name": "Lobão da Beira"
  },
  {
    "level": 3,
    "code": 182111,
    "name": "Molelos"
  },
  {
    "level": 3,
    "code": 182116,
    "name": "Parada de Gonta"
  },
  {
    "level": 3,
    "code": 182118,
    "name": "Santiago de Besteiros"
  },
  {
    "level": 3,
    "code": 182122,
    "name": "Tonda"
  },
  {
    "level": 3,
    "code": 182127,
    "name": "União das freguesias de Barreiro de Besteiros e Tourigo"
  },
  {
    "level": 3,
    "code": 182128,
    "name": "União das freguesias de Caparrosa e Silvares"
  },
  {
    "level": 3,
    "code": 182129,
    "name": "União das freguesias de Mouraz e Vila Nova da Rainha"
  },
  {
    "level": 3,
    "code": 182130,
    "name": "União das freguesias de São João do Monte e Mosteirinho"
  },
  {
    "level": 3,
    "code": 182131,
    "name": "União das freguesias de São Miguel do Outeiro e Sabugosa"
  },
  {
    "level": 3,
    "code": 182132,
    "name": "União das freguesias de Tondela e Nandufe"
  },
  {
    "level": 3,
    "code": 182133,
    "name": "União das freguesias de Vilar de Besteiros e Mosteiro de Fráguas"
  },
  {
    "level": 2,
    "code": 1822,
    "name": "Vila Nova de Paiva"
  },
  {
    "level": 3,
    "code": 182203,
    "name": "Pendilhe"
  },
  {
    "level": 3,
    "code": 182204,
    "name": "Queiriga"
  },
  {
    "level": 3,
    "code": 182205,
    "name": "Touro"
  },
  {
    "level": 3,
    "code": 182206,
    "name": "Vila Cova à Coelheira"
  },
  {
    "level": 3,
    "code": 182208,
    "name": "União das freguesias de Vila Nova de Paiva, Alhais e Fráguas"
  },
  {
    "level": 2,
    "code": 1823,
    "name": "Viseu"
  },
  {
    "level": 3,
    "code": 182301,
    "name": "Abraveses"
  },
  {
    "level": 3,
    "code": 182304,
    "name": "Bodiosa"
  },
  {
    "level": 3,
    "code": 182305,
    "name": "Calde"
  },
  {
    "level": 3,
    "code": 182306,
    "name": "Campo"
  },
  {
    "level": 3,
    "code": 182307,
    "name": "Cavernães"
  },
  {
    "level": 3,
    "code": 182310,
    "name": "Cota"
  },
  {
    "level": 3,
    "code": 182315,
    "name": "Fragosela"
  },
  {
    "level": 3,
    "code": 182316,
    "name": "Lordosa"
  },
  {
    "level": 3,
    "code": 182317,
    "name": "Silgueiros"
  },
  {
    "level": 3,
    "code": 182318,
    "name": "Mundão"
  },
  {
    "level": 3,
    "code": 182319,
    "name": "Orgens"
  },
  {
    "level": 3,
    "code": 182320,
    "name": "Povolide"
  },
  {
    "level": 3,
    "code": 182321,
    "name": "Ranhados"
  },
  {
    "level": 3,
    "code": 182322,
    "name": "Ribafeita"
  },
  {
    "level": 3,
    "code": 182323,
    "name": "Rio de Loba"
  },
  {
    "level": 3,
    "code": 182325,
    "name": "Santos Evos"
  },
  {
    "level": 3,
    "code": 182327,
    "name": "São João de Lourosa"
  },
  {
    "level": 3,
    "code": 182329,
    "name": "São Pedro de France"
  },
  {
    "level": 3,
    "code": 182335,
    "name": "União das freguesias de Barreiros e Cepões"
  },
  {
    "level": 3,
    "code": 182336,
    "name": "União das freguesias de Boa Aldeia, Farminhão e Torredeita"
  },
  {
    "level": 3,
    "code": 182337,
    "name": "Coutos de Viseu"
  },
  {
    "level": 3,
    "code": 182338,
    "name": "União das freguesias de Faíl e Vila Chã de Sá"
  },
  {
    "level": 3,
    "code": 182339,
    "name": "Repeses e São Salvador"
  },
  {
    "level": 3,
    "code": 182340,
    "name": "São Cipriano e Vil de Souto"
  },
  {
    "level": 3,
    "code": 182341,
    "name": "Viseu"
  },
  {
    "level": 2,
    "code": 1824,
    "name": "Vouzela"
  },
  {
    "level": 3,
    "code": 182401,
    "name": "Alcofra"
  },
  {
    "level": 3,
    "code": 182403,
    "name": "Campia"
  },
  {
    "level": 3,
    "code": 182407,
    "name": "Fornelo do Monte"
  },
  {
    "level": 3,
    "code": 182409,
    "name": "Queirã"
  },
  {
    "level": 3,
    "code": 182410,
    "name": "São Miguel do Mato"
  },
  {
    "level": 3,
    "code": 182411,
    "name": "Ventosa"
  },
  {
    "level": 3,
    "code": 182413,
    "name": "União das freguesias de Cambra e Carvalhal de Vermilhas"
  },
  {
    "level": 3,
    "code": 182414,
    "name": "União das freguesias de Fataunços e Figueiredo das Donas"
  },
  {
    "level": 3,
    "code": 182415,
    "name": "União das freguesias de Vouzela e Paços de Vilharigues"
  },
  {
    "level": 1,
    "code": 31,
    "name": "Ilha da Madeira"
  },
  {
    "level": 2,
    "code": 3101,
    "name": "Calheta"
  },
  {
    "level": 3,
    "code": 310101,
    "name": "Arco da Calheta"
  },
  {
    "level": 3,
    "code": 310102,
    "name": "Calheta"
  },
  {
    "level": 3,
    "code": 310103,
    "name": "Estreito da Calheta"
  },
  {
    "level": 3,
    "code": 310104,
    "name": "Fajã da Ovelha"
  },
  {
    "level": 3,
    "code": 310105,
    "name": "Jardim do Mar"
  },
  {
    "level": 3,
    "code": 310106,
    "name": "Paul do Mar"
  },
  {
    "level": 3,
    "code": 310107,
    "name": "Ponta do Pargo"
  },
  {
    "level": 3,
    "code": 310108,
    "name": "Prazeres"
  },
  {
    "level": 2,
    "code": 3102,
    "name": "Câmara de Lobos"
  },
  {
    "level": 3,
    "code": 310201,
    "name": "Câmara de Lobos"
  },
  {
    "level": 3,
    "code": 310202,
    "name": "Curral das Freiras"
  },
  {
    "level": 3,
    "code": 310203,
    "name": "Estreito de Câmara de Lobos"
  },
  {
    "level": 3,
    "code": 310204,
    "name": "Quinta Grande"
  },
  {
    "level": 3,
    "code": 310205,
    "name": "Jardim da Serra"
  },
  {
    "level": 2,
    "code": 3103,
    "name": "Funchal"
  },
  {
    "level": 3,
    "code": 310301,
    "name": "Imaculado Coração de Maria"
  },
  {
    "level": 3,
    "code": 310302,
    "name": "Monte"
  },
  {
    "level": 3,
    "code": 310303,
    "name": "Funchal (Santa Luzia)"
  },
  {
    "level": 3,
    "code": 310304,
    "name": "Funchal (Santa Maria Maior)"
  },
  {
    "level": 3,
    "code": 310305,
    "name": "Santo António"
  },
  {
    "level": 3,
    "code": 310306,
    "name": "São Gonçalo"
  },
  {
    "level": 3,
    "code": 310307,
    "name": "São Martinho"
  },
  {
    "level": 3,
    "code": 310308,
    "name": "Funchal (São Pedro)"
  },
  {
    "level": 3,
    "code": 310309,
    "name": "São Roque"
  },
  {
    "level": 3,
    "code": 310310,
    "name": "Funchal (Sé)"
  },
  {
    "level": 2,
    "code": 3104,
    "name": "Machico"
  },
  {
    "level": 3,
    "code": 310401,
    "name": "Água de Pena"
  },
  {
    "level": 3,
    "code": 310402,
    "name": "Caniçal"
  },
  {
    "level": 3,
    "code": 310403,
    "name": "Machico"
  },
  {
    "level": 3,
    "code": 310404,
    "name": "Porto da Cruz"
  },
  {
    "level": 3,
    "code": 310405,
    "name": "Santo António da Serra"
  },
  {
    "level": 2,
    "code": 3105,
    "name": "Ponta do Sol"
  },
  {
    "level": 3,
    "code": 310501,
    "name": "Canhas"
  },
  {
    "level": 3,
    "code": 310502,
    "name": "Madalena do Mar"
  },
  {
    "level": 3,
    "code": 310503,
    "name": "Ponta do Sol"
  },
  {
    "level": 2,
    "code": 3106,
    "name": "Porto Moniz"
  },
  {
    "level": 3,
    "code": 310601,
    "name": "Achadas da Cruz"
  },
  {
    "level": 3,
    "code": 310602,
    "name": "Porto Moniz"
  },
  {
    "level": 3,
    "code": 310603,
    "name": "Ribeira da Janela"
  },
  {
    "level": 3,
    "code": 310604,
    "name": "Seixal"
  },
  {
    "level": 2,
    "code": 3107,
    "name": "Ribeira Brava"
  },
  {
    "level": 3,
    "code": 310701,
    "name": "Campanário"
  },
  {
    "level": 3,
    "code": 310702,
    "name": "Ribeira Brava"
  },
  {
    "level": 3,
    "code": 310703,
    "name": "Serra de Água"
  },
  {
    "level": 3,
    "code": 310704,
    "name": "Tábua"
  },
  {
    "level": 2,
    "code": 3108,
    "name": "Santa Cruz"
  },
  {
    "level": 3,
    "code": 310802,
    "name": "Camacha"
  },
  {
    "level": 3,
    "code": 310803,
    "name": "Caniço"
  },
  {
    "level": 3,
    "code": 310804,
    "name": "Gaula"
  },
  {
    "level": 3,
    "code": 310805,
    "name": "Santa Cruz"
  },
  {
    "level": 3,
    "code": 310806,
    "name": "Santo António da Serra"
  },
  {
    "level": 2,
    "code": 3109,
    "name": "Santana"
  },
  {
    "level": 3,
    "code": 310901,
    "name": "Arco de São Jorge"
  },
  {
    "level": 3,
    "code": 310902,
    "name": "Faial"
  },
  {
    "level": 3,
    "code": 310903,
    "name": "Santana"
  },
  {
    "level": 3,
    "code": 310904,
    "name": "São Jorge"
  },
  {
    "level": 3,
    "code": 310905,
    "name": "São Roque do Faial"
  },
  {
    "level": 3,
    "code": 310906,
    "name": "Ilha"
  },
  {
    "level": 2,
    "code": 3110,
    "name": "São Vicente"
  },
  {
    "level": 3,
    "code": 311001,
    "name": "Boa Ventura"
  },
  {
    "level": 3,
    "code": 311002,
    "name": "Ponta Delgada"
  },
  {
    "level": 3,
    "code": 311003,
    "name": "São Vicente"
  },
  {
    "level": 1,
    "code": 32,
    "name": "Ilha de Porto Santo"
  },
  {
    "level": 2,
    "code": 3201,
    "name": "Porto Santo"
  },
  {
    "level": 3,
    "code": 320101,
    "name": "Porto Santo"
  },
  {
    "level": 1,
    "code": 41,
    "name": "Ilha de Santa Maria"
  },
  {
    "level": 2,
    "code": 4101,
    "name": "Vila do Porto"
  },
  {
    "level": 3,
    "code": 410101,
    "name": "Almagreira"
  },
  {
    "level": 3,
    "code": 410102,
    "name": "Santa Bárbara"
  },
  {
    "level": 3,
    "code": 410103,
    "name": "Santo Espírito"
  },
  {
    "level": 3,
    "code": 410104,
    "name": "São Pedro"
  },
  {
    "level": 3,
    "code": 410105,
    "name": "Vila do Porto"
  },
  {
    "level": 1,
    "code": 42,
    "name": "Ilha de São Miguel"
  },
  {
    "level": 2,
    "code": 4201,
    "name": "Lagoa"
  },
  {
    "level": 3,
    "code": 420101,
    "name": "Água de Pau"
  },
  {
    "level": 3,
    "code": 420102,
    "name": "Cabouco"
  },
  {
    "level": 3,
    "code": 420103,
    "name": "Lagoa (Nossa Senhora do Rosário)"
  },
  {
    "level": 3,
    "code": 420104,
    "name": "Lagoa (Santa Cruz)"
  },
  {
    "level": 3,
    "code": 420105,
    "name": "Ribeira Chã"
  },
  {
    "level": 2,
    "code": 4202,
    "name": "Nordeste"
  },
  {
    "level": 3,
    "code": 420201,
    "name": "Achada"
  },
  {
    "level": 3,
    "code": 420202,
    "name": "Achadinha"
  },
  {
    "level": 3,
    "code": 420203,
    "name": "Lomba da Fazenda"
  },
  {
    "level": 3,
    "code": 420204,
    "name": "Nordeste"
  },
  {
    "level": 3,
    "code": 420206,
    "name": "Salga"
  },
  {
    "level": 3,
    "code": 420207,
    "name": "Santana"
  },
  {
    "level": 3,
    "code": 420208,
    "name": "Algarvia"
  },
  {
    "level": 3,
    "code": 420209,
    "name": "Santo António de Nordestinho"
  },
  {
    "level": 3,
    "code": 420210,
    "name": "São Pedro de Nordestinho"
  },
  {
    "level": 2,
    "code": 4203,
    "name": "Ponta Delgada"
  },
  {
    "level": 3,
    "code": 420301,
    "name": "Arrifes"
  },
  {
    "level": 3,
    "code": 420303,
    "name": "Candelária"
  },
  {
    "level": 3,
    "code": 420304,
    "name": "Capelas"
  },
  {
    "level": 3,
    "code": 420305,
    "name": "Covoada"
  },
  {
    "level": 3,
    "code": 420306,
    "name": "Fajã de Baixo"
  },
  {
    "level": 3,
    "code": 420307,
    "name": "Fajã de Cima"
  },
  {
    "level": 3,
    "code": 420308,
    "name": "Fenais da Luz"
  },
  {
    "level": 3,
    "code": 420309,
    "name": "Feteiras"
  },
  {
    "level": 3,
    "code": 420310,
    "name": "Ginetes"
  },
  {
    "level": 3,
    "code": 420311,
    "name": "Mosteiros"
  },
  {
    "level": 3,
    "code": 420312,
    "name": "Ponta Delgada (São Sebastião)"
  },
  {
    "level": 3,
    "code": 420313,
    "name": "Ponta Delgada (São José)"
  },
  {
    "level": 3,
    "code": 420314,
    "name": "Ponta Delgada (São Pedro)"
  },
  {
    "level": 3,
    "code": 420315,
    "name": "Relva"
  },
  {
    "level": 3,
    "code": 420316,
    "name": "Remédios"
  },
  {
    "level": 3,
    "code": 420317,
    "name": "Rosto do Cão (Livramento)"
  },
  {
    "level": 3,
    "code": 420318,
    "name": "Rosto do Cão (São Roque)"
  },
  {
    "level": 3,
    "code": 420319,
    "name": "Santa Bárbara"
  },
  {
    "level": 3,
    "code": 420320,
    "name": "Santo António"
  },
  {
    "level": 3,
    "code": 420321,
    "name": "São Vicente Ferreira"
  },
  {
    "level": 3,
    "code": 420322,
    "name": "Sete Cidades"
  },
  {
    "level": 3,
    "code": 420323,
    "name": "Ajuda da Bretanha"
  },
  {
    "level": 3,
    "code": 420324,
    "name": "Pilar da Bretanha"
  },
  {
    "level": 3,
    "code": 420325,
    "name": "Santa Clara"
  },
  {
    "level": 2,
    "code": 4204,
    "name": "Povoação"
  },
  {
    "level": 3,
    "code": 420401,
    "name": "Água Retorta"
  },
  {
    "level": 3,
    "code": 420402,
    "name": "Faial da Terra"
  },
  {
    "level": 3,
    "code": 420403,
    "name": "Furnas"
  },
  {
    "level": 3,
    "code": 420404,
    "name": "Nossa Senhora dos Remédios"
  },
  {
    "level": 3,
    "code": 420405,
    "name": "Povoação"
  },
  {
    "level": 3,
    "code": 420406,
    "name": "Ribeira Quente"
  },
  {
    "level": 2,
    "code": 4205,
    "name": "Ribeira Grande"
  },
  {
    "level": 3,
    "code": 420501,
    "name": "Calhetas"
  },
  {
    "level": 3,
    "code": 420502,
    "name": "Fenais da Ajuda"
  },
  {
    "level": 3,
    "code": 420503,
    "name": "Lomba da Maia"
  },
  {
    "level": 3,
    "code": 420504,
    "name": "Lomba de São Pedro"
  },
  {
    "level": 3,
    "code": 420505,
    "name": "Maia"
  },
  {
    "level": 3,
    "code": 420506,
    "name": "Pico da Pedra"
  },
  {
    "level": 3,
    "code": 420507,
    "name": "Porto Formoso"
  },
  {
    "level": 3,
    "code": 420508,
    "name": "Rabo de Peixe"
  },
  {
    "level": 3,
    "code": 420509,
    "name": "Ribeira Grande (Conceição)"
  },
  {
    "level": 3,
    "code": 420510,
    "name": "Ribeira Grande (Matriz)"
  },
  {
    "level": 3,
    "code": 420511,
    "name": "Ribeira Seca"
  },
  {
    "level": 3,
    "code": 420512,
    "name": "Ribeirinha"
  },
  {
    "level": 3,
    "code": 420513,
    "name": "Santa Bárbara"
  },
  {
    "level": 3,
    "code": 420514,
    "name": "São Brás"
  },
  {
    "level": 2,
    "code": 4206,
    "name": "Vila Franca do Campo"
  },
  {
    "level": 3,
    "code": 420601,
    "name": "Água de Alto"
  },
  {
    "level": 3,
    "code": 420602,
    "name": "Ponta Garça"
  },
  {
    "level": 3,
    "code": 420603,
    "name": "Ribeira das Tainhas"
  },
  {
    "level": 3,
    "code": 420604,
    "name": "Vila Franca do Campo (São Miguel)"
  },
  {
    "level": 3,
    "code": 420605,
    "name": "Vila Franca do Campo (São Pedro)"
  },
  {
    "level": 3,
    "code": 420606,
    "name": "Ribeira Seca"
  },
  {
    "level": 1,
    "code": 43,
    "name": "Ilha Terceira"
  },
  {
    "level": 2,
    "code": 4301,
    "name": "Angra do Heroísmo"
  },
  {
    "level": 3,
    "code": 430101,
    "name": "Altares"
  },
  {
    "level": 3,
    "code": 430102,
    "name": "Angra (Nossa Senhora da Conceição)"
  },
  {
    "level": 3,
    "code": 430103,
    "name": "Angra (Santa Luzia)"
  },
  {
    "level": 3,
    "code": 430104,
    "name": "Angra (São Pedro)"
  },
  {
    "level": 3,
    "code": 430105,
    "name": "Angra (Sé)"
  },
  {
    "level": 3,
    "code": 430106,
    "name": "Cinco Ribeiras"
  },
  {
    "level": 3,
    "code": 430107,
    "name": "Doze Ribeiras"
  },
  {
    "level": 3,
    "code": 430108,
    "name": "Feteira"
  },
  {
    "level": 3,
    "code": 430109,
    "name": "Porto Judeu"
  },
  {
    "level": 3,
    "code": 430110,
    "name": "Posto Santo"
  },
  {
    "level": 3,
    "code": 430111,
    "name": "Raminho"
  },
  {
    "level": 3,
    "code": 430112,
    "name": "Ribeirinha"
  },
  {
    "level": 3,
    "code": 430113,
    "name": "Santa Bárbara"
  },
  {
    "level": 3,
    "code": 430114,
    "name": "São Bartolomeu de Regatos"
  },
  {
    "level": 3,
    "code": 430115,
    "name": "São Bento"
  },
  {
    "level": 3,
    "code": 430116,
    "name": "São Mateus da Calheta"
  },
  {
    "level": 3,
    "code": 430117,
    "name": "Serreta"
  },
  {
    "level": 3,
    "code": 430118,
    "name": "Terra Chã"
  },
  {
    "level": 3,
    "code": 430119,
    "name": "Vila de São Sebastião"
  },
  {
    "level": 2,
    "code": 4302,
    "name": "Vila da Praia da Vitória"
  },
  {
    "level": 3,
    "code": 430201,
    "name": "Agualva"
  },
  {
    "level": 3,
    "code": 430202,
    "name": "Biscoitos"
  },
  {
    "level": 3,
    "code": 430203,
    "name": "Cabo da Praia"
  },
  {
    "level": 3,
    "code": 430204,
    "name": "Fonte do Bastardo"
  },
  {
    "level": 3,
    "code": 430205,
    "name": "Fontinhas"
  },
  {
    "level": 3,
    "code": 430206,
    "name": "Lajes"
  },
  {
    "level": 3,
    "code": 430207,
    "name": "Praia da Vitória (Santa Cruz)"
  },
  {
    "level": 3,
    "code": 430208,
    "name": "Quatro Ribeiras"
  },
  {
    "level": 3,
    "code": 430209,
    "name": "São Brás"
  },
  {
    "level": 3,
    "code": 430210,
    "name": "Vila Nova"
  },
  {
    "level": 3,
    "code": 430211,
    "name": "Porto Martins"
  },
  {
    "level": 1,
    "code": 44,
    "name": "Ilha Graciosa"
  },
  {
    "level": 2,
    "code": 4401,
    "name": "Santa Cruz da Graciosa"
  },
  {
    "level": 3,
    "code": 440101,
    "name": "Guadalupe"
  },
  {
    "level": 3,
    "code": 440102,
    "name": "Luz"
  },
  {
    "level": 3,
    "code": 440103,
    "name": "São Mateus"
  },
  {
    "level": 3,
    "code": 440104,
    "name": "Santa Cruz da Graciosa"
  },
  {
    "level": 1,
    "code": 45,
    "name": "Ilha de São Jorge"
  },
  {
    "level": 2,
    "code": 4501,
    "name": "Calheta"
  },
  {
    "level": 3,
    "code": 450101,
    "name": "Calheta"
  },
  {
    "level": 3,
    "code": 450102,
    "name": "Norte Pequeno"
  },
  {
    "level": 3,
    "code": 450103,
    "name": "Ribeira Seca"
  },
  {
    "level": 3,
    "code": 450104,
    "name": "Santo Antão"
  },
  {
    "level": 3,
    "code": 450105,
    "name": "Topo (Nossa Senhora do Rosário)"
  },
  {
    "level": 2,
    "code": 4502,
    "name": "Velas"
  },
  {
    "level": 3,
    "code": 450201,
    "name": "Manadas (Santa Bárbara)"
  },
  {
    "level": 3,
    "code": 450202,
    "name": "Norte Grande (Neves)"
  },
  {
    "level": 3,
    "code": 450203,
    "name": "Rosais"
  },
  {
    "level": 3,
    "code": 450204,
    "name": "Santo Amaro"
  },
  {
    "level": 3,
    "code": 450205,
    "name": "Urzelina (São Mateus)"
  },
  {
    "level": 3,
    "code": 450206,
    "name": "Velas (São Jorge)"
  },
  {
    "level": 1,
    "code": 46,
    "name": "Ilha do Pico"
  },
  {
    "level": 2,
    "code": 4601,
    "name": "Lajes do Pico"
  },
  {
    "level": 3,
    "code": 460101,
    "name": "Calheta de Nesquim"
  },
  {
    "level": 3,
    "code": 460102,
    "name": "Lajes do Pico"
  },
  {
    "level": 3,
    "code": 460103,
    "name": "Piedade"
  },
  {
    "level": 3,
    "code": 460104,
    "name": "Ribeiras"
  },
  {
    "level": 3,
    "code": 460105,
    "name": "Ribeirinha"
  },
  {
    "level": 3,
    "code": 460106,
    "name": "São João"
  },
  {
    "level": 2,
    "code": 4602,
    "name": "Madalena"
  },
  {
    "level": 3,
    "code": 460201,
    "name": "Bandeiras"
  },
  {
    "level": 3,
    "code": 460202,
    "name": "Candelária"
  },
  {
    "level": 3,
    "code": 460203,
    "name": "Criação Velha"
  },
  {
    "level": 3,
    "code": 460204,
    "name": "Madalena"
  },
  {
    "level": 3,
    "code": 460205,
    "name": "São Caetano"
  },
  {
    "level": 3,
    "code": 460206,
    "name": "São Mateus"
  },
  {
    "level": 2,
    "code": 4603,
    "name": "São Roque do Pico"
  },
  {
    "level": 3,
    "code": 460301,
    "name": "Prainha"
  },
  {
    "level": 3,
    "code": 460302,
    "name": "Santa Luzia"
  },
  {
    "level": 3,
    "code": 460303,
    "name": "Santo Amaro"
  },
  {
    "level": 3,
    "code": 460304,
    "name": "Santo António"
  },
  {
    "level": 3,
    "code": 460305,
    "name": "São Roque do Pico"
  },
  {
    "level": 1,
    "code": 47,
    "name": "Ilha do Faial"
  },
  {
    "level": 2,
    "code": 4701,
    "name": "Horta"
  },
  {
    "level": 3,
    "code": 470101,
    "name": "Capelo"
  },
  {
    "level": 3,
    "code": 470102,
    "name": "Castelo Branco"
  },
  {
    "level": 3,
    "code": 470103,
    "name": "Cedros"
  },
  {
    "level": 3,
    "code": 470104,
    "name": "Feteira"
  },
  {
    "level": 3,
    "code": 470105,
    "name": "Flamengos"
  },
  {
    "level": 3,
    "code": 470106,
    "name": "Horta (Angústias)"
  },
  {
    "level": 3,
    "code": 470107,
    "name": "Horta (Conceição)"
  },
  {
    "level": 3,
    "code": 470108,
    "name": "Horta (Matriz)"
  },
  {
    "level": 3,
    "code": 470109,
    "name": "Pedro Miguel"
  },
  {
    "level": 3,
    "code": 470110,
    "name": "Praia do Almoxarife"
  },
  {
    "level": 3,
    "code": 470111,
    "name": "Praia do Norte"
  },
  {
    "level": 3,
    "code": 470112,
    "name": "Ribeirinha"
  },
  {
    "level": 3,
    "code": 470113,
    "name": "Salão"
  },
  {
    "level": 1,
    "code": 48,
    "name": "Ilha das Flores"
  },
  {
    "level": 2,
    "code": 4801,
    "name": "Lajes das Flores"
  },
  {
    "level": 3,
    "code": 480101,
    "name": "Fajã Grande"
  },
  {
    "level": 3,
    "code": 480102,
    "name": "Fajãzinha"
  },
  {
    "level": 3,
    "code": 480103,
    "name": "Fazenda"
  },
  {
    "level": 3,
    "code": 480104,
    "name": "Lajedo"
  },
  {
    "level": 3,
    "code": 480105,
    "name": "Lajes das Flores"
  },
  {
    "level": 3,
    "code": 480106,
    "name": "Lomba"
  },
  {
    "level": 3,
    "code": 480107,
    "name": "Mosteiro"
  },
  {
    "level": 2,
    "code": 4802,
    "name": "Santa Cruz das Flores"
  },
  {
    "level": 3,
    "code": 480201,
    "name": "Caveira"
  },
  {
    "level": 3,
    "code": 480202,
    "name": "Cedros"
  },
  {
    "level": 3,
    "code": 480203,
    "name": "Ponta Delgada"
  },
  {
    "level": 3,
    "code": 480204,
    "name": "Santa Cruz das Flores"
  },
  {
    "level": 1,
    "code": 49,
    "name": "Ilha do Corvo"
  },
  {
    "level": 2,
    "code": 4901,
    "name": "Corvo"
  },
  {
    "level": 3,
    "code": 490101,
    "name": "Corvo"
  }
]');

        foreach ($locations as $l) {
            $location = new Location();
            $location->level = $l->level;
            $location->name = $l->name;
            $location->code = $l->code;
            $location->save();
        }
    }
}

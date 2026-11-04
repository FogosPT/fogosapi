<?php

namespace App\Console\Commands;

use App\Support\SeismicPlaces;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

/**
 * Regenerates resources/data/seismic/places-pt.json from Wikidata (CC0):
 * current municipalities (Q13217644, coordinate = seat) and civil parishes
 * (Q1131296) of Portugal, without the dissolved ones (P576). Lisbon's parishes
 * are attached to the city item (Q597) rather than to a municipality, so the
 * parish query accepts it explicitly.
 */
class ImportSeismicPlaces extends Command
{
    protected $signature = 'seismic:places-import';

    protected $description = 'Rebuild the Portuguese places gazetteer used for "nearest places" from Wikidata';

    /** Current municipalities; their coordinate is the seat. */
    public const MUNICIPALITIES = <<<'SPARQL'
        SELECT ?item ?name ?coord ?pop WHERE {
          ?item wdt:P31 wd:Q13217644 ; wdt:P625 ?coord ; rdfs:label ?name .
          FILTER(LANG(?name) = "pt")
          FILTER NOT EXISTS { ?item wdt:P576 [] }
          # Lafões (a historical sub-region) is also tagged as a municipality.
          FILTER NOT EXISTS { ?item wdt:P31 wd:Q82794 }
          OPTIONAL { ?item wdt:P1082 ?pop }
        }
        SPARQL;

    /** Current civil parishes and their municipality. */
    public const PARISHES = <<<'SPARQL'
        SELECT ?item ?name ?munName ?coord ?pop WHERE {
          ?item wdt:P31 wd:Q1131296 ; wdt:P625 ?coord ; wdt:P131 ?mun ; rdfs:label ?name .
          # Lisbon's parishes point at the city (Q597), which is not typed as a municipality.
          { ?mun wdt:P31 wd:Q13217644 } UNION { VALUES ?mun { wd:Q597 } }
          ?mun rdfs:label ?munName .
          FILTER(LANG(?name) = "pt" && LANG(?munName) = "pt")
          FILTER NOT EXISTS { ?item wdt:P576 [] }
          FILTER NOT EXISTS { ?mun wdt:P576 [] }
          OPTIONAL { ?item wdt:P1082 ?pop }
        }
        SPARQL;

    public function handle(): int
    {
        $places = [];
        foreach ([['c', self::MUNICIPALITIES], ['f', self::PARISHES]] as [$kind, $query]) {
            foreach ($this->sparql($query) as $r) {
                if (!preg_match('/Point\(([-\d.]+) ([-\d.]+)\)/', $r['coord']['value'] ?? '', $m)) {
                    continue;
                }
                $id = basename($r['item']['value']);
                $pop = isset($r['pop']) ? (int) $r['pop']['value'] : null;
                // One entry per item (several coordinates/populations → first coordinate, largest population).
                if (isset($places[$id])) {
                    $places[$id][5] = max($places[$id][5] ?? 0, $pop ?? 0) ?: null;

                    continue;
                }
                $places[$id] = [
                    $r['name']['value'],
                    $kind === 'c' ? $r['name']['value'] : $r['munName']['value'],
                    $kind,
                    round((float) $m[2], 4),
                    round((float) $m[1], 4),
                    $pop,
                ];
            }
        }

        ksort($places, SORT_NATURAL);
        $kinds = array_count_values(array_column($places, 2));
        $json = json_encode([
            'source' => 'Wikidata (CC0), '.gmdate('Y-m-d'),
            'fields' => ['name', 'municipality', 'kind (c=concelho, f=freguesia)', 'lat', 'lon', 'population'],
            'places' => array_values($places),
        ], JSON_UNESCAPED_UNICODE);

        file_put_contents(SeismicPlaces::path(), $json."\n");
        $this->info(sprintf('%d concelhos, %d freguesias → %s', $kinds['c'] ?? 0, $kinds['f'] ?? 0, SeismicPlaces::path()));

        return self::SUCCESS;
    }

    private function sparql(string $query): array
    {
        $res = (new Client(['timeout' => 180]))->get('https://query.wikidata.org/sparql', [
            'query' => ['query' => $query, 'format' => 'json'],
            'headers' => ['Accept' => 'application/sparql-results+json', 'User-Agent' => 'sismos.pt/1.0 (+https://sismos.pt)'],
        ]);

        return json_decode($res->getBody()->getContents(), true)['results']['bindings'] ?? [];
    }
}

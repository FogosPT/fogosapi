<?php

namespace App\Console\Commands;

use App\Support\SeismicPlaces;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Computes `nearestPlaces` for earthquakes already stored (new and revised
 * ones get it from EarthquakeMerger). Streams the collection and writes in
 * bulk, so it is fine for the full historical catalogue. Idempotent.
 */
class FillSeismicPlaces extends Command
{
    protected $signature = 'seismic:places
        {--chunk=1000 : documents per bulk write}
        {--missing : only earthquakes without nearestPlaces}';

    protected $description = 'Fill the nearest places of stored earthquakes';

    public function handle(): int
    {
        $collection = DB::connection('mongodb')->getMongoDB()->selectCollection('earthquakes');
        $filter = $this->option('missing') ? ['nearestPlaces' => ['$exists' => false]] : [];
        $chunk = max(1, (int) $this->option('chunk'));
        $total = $collection->countDocuments($filter);
        $started = microtime(true);

        $bar = $this->output->createProgressBar($total);
        $ops = [];
        $done = 0;
        foreach ($collection->find($filter, ['projection' => ['lat' => 1, 'lon' => 1], 'noCursorTimeout' => true]) as $doc) {
            if (!isset($doc['lat'], $doc['lon'])) {
                continue;
            }
            $ops[] = ['updateOne' => [
                ['_id' => $doc['_id']],
                ['$set' => ['nearestPlaces' => SeismicPlaces::nearest((float) $doc['lat'], (float) $doc['lon'])]],
            ]];
            if (count($ops) >= $chunk) {
                $collection->bulkWrite($ops, ['ordered' => false]);
                $done += count($ops);
                $bar->advance(count($ops));
                $ops = [];
            }
        }
        if ($ops) {
            $collection->bulkWrite($ops, ['ordered' => false]);
            $done += count($ops);
            $bar->advance(count($ops));
        }
        $bar->finish();
        $this->newLine();
        $this->info(sprintf('%d earthquakes in %.1fs', $done, microtime(true) - $started));

        return self::SUCCESS;
    }
}

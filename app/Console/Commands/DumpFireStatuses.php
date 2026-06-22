<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\IncidentStatusHistory;
use App\Resources\V1\HistoryStatusResource;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class DumpFireStatuses extends Command
{
    protected $signature = 'fogospt:dump-fire-statuses '
        . '{--output=storage/app/fire-statuses.json : Output file path (absolute or relative to project root)} '
        . '{--chunk=200 : Incidents per DB chunk}';

    protected $description = 'Dump a JSON file simulating /fires/status?id=<sadoId>&extended=1 for every isFire=true incident';

    public function handle(): int
    {
        $output = $this->option('output');
        if (! str_starts_with($output, DIRECTORY_SEPARATOR)) {
            $output = base_path($output);
        }

        $dir = dirname($output);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error("Cannot create output directory: {$dir}");
            return self::FAILURE;
        }

        $handle = fopen($output, 'w');
        if ($handle === false) {
            $this->error("Cannot open {$output} for writing");
            return self::FAILURE;
        }

        $request = Request::create('/fires/status', 'GET');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $total = Incident::isFire()->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        fwrite($handle, "{\n");
        $first = true;
        $written = 0;

        Incident::isFire()->orderBy('_id')->chunk($chunkSize, function ($incidents) use ($handle, &$first, &$written, $request, $bar) {
            foreach ($incidents as $incident) {
                $fireId = $incident['id'] ?? $incident['_id'] ?? null;
                if ($fireId === null) {
                    $bar->advance();
                    continue;
                }

                $statusHistory = IncidentStatusHistory::whereFireId((string) $fireId)
                    ->orderBy('created', 'desc')
                    ->get();

                $data = HistoryStatusResource::collection($statusHistory)->toArray($request);

                $dateTime = $incident['dateTime'] ?? null;
                if ($dateTime instanceof \DateTimeInterface) {
                    $createdSec = $dateTime->getTimestamp();
                } elseif (is_string($dateTime) && $dateTime !== '') {
                    $createdSec = strtotime($dateTime);
                } else {
                    $createdSec = null;
                }

                $data[] = [
                    'label' => trim(($incident['date'] ?? '') . ' ' . ($incident['hour'] ?? '')),
                    'status' => 'Início',
                    'statusCode' => 99,
                    'created' => $createdSec,
                ];

                $payload = json_encode(
                    ['success' => true, 'data' => $data],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                $key = (string) ($incident['sadoId'] ?? $fireId);
                $encodedKey = json_encode($key, JSON_UNESCAPED_UNICODE);

                if (! $first) {
                    fwrite($handle, ",\n");
                }
                $first = false;
                fwrite($handle, "  {$encodedKey}: {$payload}");

                $written++;
                $bar->advance();
            }
        });

        fwrite($handle, "\n}\n");
        fclose($handle);
        $bar->finish();
        $this->newLine();

        $this->info("Wrote {$written} incident status entries to {$output}");
        return self::SUCCESS;
    }
}

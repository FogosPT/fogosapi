<?php

namespace App\Jobs;

use App\Models\Incident;
use Illuminate\Support\Facades\Log;

// Compara incidentes com active a true com a lista que aparece no site da ANEPC
// e adicionalmente marca inactivos os que o ICNF já reporta como "Extinto".
// Queries de consulta devem ter todos active = true
class CheckIsActive extends Job
{
    public $data;
    public $activeIds;

    /**
     * Create a new job instance.
     *
     * @param mixed $data
     */
    public function __construct($data)
    {
        $this->data = $data;
        $activeIds = [];
        foreach ($this->data as $d) {
            $activeIds[] = $d['numero_sado'];
        }

        $this->activeIds = $activeIds;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $incidents = Incident::where('active', true)->whereNotIn('id', $this->activeIds)->get();

        foreach ($incidents as $incident) {
            $incident->active = false;
            $incident->save();
        }

        $extintoIds = $this->fetchIcnfExtintoIds();

        if (!empty($extintoIds)) {
            $incidents = Incident::where('active', true)->whereIn('id', $extintoIds)->get();

            foreach ($incidents as $incident) {
                $incident->active = false;
                $incident->save();
            }
        }
    }

    private function fetchIcnfExtintoIds(): array
    {
        $url = 'https://fogos.icnf.pt/localizador/faztable.asp';

        $options = [
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
            ],
            'verify' => false,
        ];

        try {
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $url, $options);
            $body = str_replace(PHP_EOL, '', $res->getBody()->getContents());
        } catch (\Throwable $e) {
            Log::error('CheckIsActive: ICNF fetch failed', ['message' => $e->getMessage()]);
            return [];
        }

        preg_match_all('/\[(.*?)\]/', $body, $result);

        $extinto = [];
        $i = 0;
        foreach ($result[1] as $r) {
            if ($i === 0 || $i === 1) {
                ++$i;
                continue;
            }
            ++$i;

            $rr = explode("',", $r);
            $icnfEstado = isset($rr[12]) ? ltrim($rr[12], "'") : '';

            if ($icnfEstado !== 'Extinto') {
                continue;
            }

            $id = strip_tags(str_replace("'", '', $rr[0]));

            if (!ctype_digit($id)) {
                continue;
            }

            $extinto[] = $id;
        }

        return $extinto;
    }
}

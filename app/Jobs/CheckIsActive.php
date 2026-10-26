<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Support\AnepcFeedHealth;
use App\Tools\ICNFTool;
use Illuminate\Support\Facades\Log;

// Compara incidentes com active a true com a lista que aparece no site da ANEPC
// e adicionalmente marca inactivos os que o ICNF já reporta como "Extinto".
// Despachado pelo ProcessOcorrenciasSite, não corre enquanto esse feed estiver
// parado (ver AnepcFeedHealth).
// Queries de consulta devem ter todos active = true
class CheckIsActive extends Job
{
    public $data;
    public $activeIds;

    /**
     * Only callers whose list comes from the feed tracked by AnepcFeedHealth
     * (OcorrenciasSite) should skip cleanup when that feed is stale.
     */
    public bool $guardAnepcFeedHealth = false;

    /**
     * Create a new job instance.
     *
     * @param mixed $data
     */
    public function __construct($data, bool $guardAnepcFeedHealth = false)
    {
        $this->data = $data;
        $this->guardAnepcFeedHealth = $guardAnepcFeedHealth;
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
        // A frozen ANEPC list would wipe every fire ICNF reported meanwhile;
        // while the feed is stale ProcessICNFNewFireData owns the lifecycle.
        if ($this->guardAnepcFeedHealth && AnepcFeedHealth::isStale()) {
            Log::info('CheckIsActive: ANEPC feed is stale, skipping');

            return;
        }

        $incidents = Incident::where('active', true)->whereNotIn('id', $this->activeIds)->get();

        foreach ($incidents as $incident) {
            $incident->active = false;
            $incident->save();
        }

        $extintoIds = array_diff($this->fetchIcnfExtintoIds(), $this->activeIds);

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
        try {
            $occurrences = ICNFTool::fetchOccurrences();
        } catch (\Throwable $e) {
            Log::error('CheckIsActive: ICNF fetch failed', ['message' => $e->getMessage()]);
            return [];
        }

        $extinto = array_filter($occurrences, fn ($o) => $o['estado'] === ICNFTool::ESTADO_EXTINTO);

        return array_column($extinto, 'id');
    }
}

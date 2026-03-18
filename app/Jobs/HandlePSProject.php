<?php

namespace App\Jobs;

use App\Models\Incident;
use Illuminate\Support\Facades\Log;
use PhpImap\Mailbox;
use voku\helper\UTF8;

class HandlePSProject extends Job
{
    public $incident;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Incident $incident)
    {
        $this->incident = $incident;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $apiToken = env('PS_PROJECT_TELEGRAM_TOKEN');

        $status = "⚠️ NOVA OCORRÊNCIA NA ÁREA OPERACIONAL:

{$this->incident->date} - {$this->incident->hour} - {$this->incident->localidade}

{$this->incident->natureza}

🧑‍🚒 - {$this->incident->man}
🚒 - {$this->incident->terrain}
🚁 - {$this->incident->aerial}
🚤 - {$this->incident->meios_aquaticos}

Estado: {$this->incident->status}
Nº SADO DA OCORRÊNCIA: {$this->incident->sadoId}

https://fogos.pt/fogo/{$this->incident->sadoId}/detalhe
";


        $data = [
            'chat_id' => env('PS_PROJECT_TELEGRAM_CHANNEL'),
            'text' => $status,
        ];

        \App\Tools\TelegramTool::sendMessage($apiToken, $data);
    }
}

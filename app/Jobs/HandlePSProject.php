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

{$this->incident->date} - {$this->incident->hour} // {$this->incident->natureza}, {$this->incident->localidade}, {$this->incident->concelho}

✍🏻 Nº SADO DA OCORRÊNCIA: {$this->incident->sadoId}";

        $data = [
            'chat_id' => env('PS_PROJECT_TELEGRAM_CHANNEL'),
            'text' => $status,
        ];

        file_get_contents("https://api.telegram.org/bot{$apiToken}/sendMessage?".http_build_query($data));
    }
}

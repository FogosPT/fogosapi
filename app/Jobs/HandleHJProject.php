<?php

namespace App\Jobs;

use App\Models\Incident;
use Illuminate\Support\Facades\Log;
use PhpImap\Mailbox;
use voku\helper\UTF8;

class HandleHJProject extends Job
{
    public $incident;
    public $telegramChannel;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Incident $incident, $telegramChannel)
    {
        $this->incident = $incident;
        $this->telegramChannel = $telegramChannel;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $apiToken = env('HL_PROJECT_TELEGRAM_TOKEN');

        $status = "⚠️ NOVA OCORRÊNCIA NA ÁREA OPERACIONAL:

{$this->incident->date} - {$this->incident->hour} // {$this->incident->natureza}, {$this->incident->localidade}, {$this->incident->concelho}

✍🏻 Nº SADO DA OCORRÊNCIA: {$this->incident->sadoId}";

        $data = [
            'chat_id' => env('HL_PROJECT_TELEGRAM_CHANNEL'),
            'text' => $status,
            'message_thread_id' => $this->telegramChannel
        ];

        try {
            $client = new \GuzzleHttp\Client();
            $client->post("https://api.telegram.org/bot{$apiToken}/sendMessage", [
                'json' => $data,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('HJProject Telegram send failed: ' . $e->getMessage());
        }
    }
}

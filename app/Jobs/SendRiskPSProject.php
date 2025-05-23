<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\RCM;
use Illuminate\Support\Facades\Log;
use PhpImap\Mailbox;
use voku\helper\UTF8;

class SendRiskPSProject extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $apiToken = env('PS_PROJECT_TELEGRAM_TOKEN');

        $rcm = RCM::where('dico',  env('PS_PROJECT_TELEGRAM_CHANNEL_1_DICOS'))
            ->limit(1)
            ->orderBy('created', 'desc')
            ->first();

        $status = "Risco de incêndio para hoje: {$rcm->getRiskTodayEmoji()} - {$rcm->hoje}";

        $data = [
            'chat_id' => env('PS_PROJECT_TELEGRAM_CHANNEL'),
            'text' => $status,
        ];

        file_get_contents("https://api.telegram.org/bot{$apiToken}/sendMessage?".http_build_query($data));
    }
}

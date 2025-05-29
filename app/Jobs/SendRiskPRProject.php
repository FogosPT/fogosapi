<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\RCM;
use Illuminate\Support\Facades\Log;
use PhpImap\Mailbox;
use voku\helper\UTF8;

class SendRiskPRProject extends Job
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
        $apiToken = env('PR_PROJECT_TELEGRAM_TOKEN');

        $prDico1 = explode(',', env('PR_PROJECT_TELEGRAM_CHANNEL_1_DICOS'));
        $prDico2 = explode(',', env('PR_PROJECT_TELEGRAM_CHANNEL_2_DICOS'));
        $prDico3 = explode(',', env('PR_PROJECT_TELEGRAM_CHANNEL_3_DICOS'));

        foreach($prDico1 as $prDico) {
            $rcm = RCM::where('dico', $prDico)
                ->limit(1)
                ->orderBy('created', 'desc')
                ->first();

            $status = "Risco de incêndio para hoje: {$rcm->getRiskTodayEmoji()} {$rcm->hoje}";

            $data = [
                'chat_id' => env('PR_PROJECT_TELEGRAM_CHANNEL'),
                'text' => $status,
                'message_thread_id' => env('PR_PROJECT_TELEGRAM_CHANNEL_1')
            ];

            file_get_contents("https://api.telegram.org/bot{$apiToken}/sendMessage?".http_build_query($data));
        }

        foreach($prDico2 as $prDico) {
            $rcm = RCM::where('dico', $prDico)
                ->limit(1)
                ->orderBy('created', 'desc')
                ->first();

            $status = "Risco de incêndio para hoje: {$rcm->getRiskTodayEmoji()} {$rcm->hoje}";

            $data = [
                'chat_id' => env('PR_PROJECT_TELEGRAM_CHANNEL'),
                'text' => $status,
                'message_thread_id' => env('PR_PROJECT_TELEGRAM_CHANNEL_2')
            ];

            file_get_contents("https://api.telegram.org/bot{$apiToken}/sendMessage?".http_build_query($data));
        }

        foreach($prDico3 as $prDico) {
            $rcm = RCM::where('dico', $prDico)
                ->limit(1)
                ->orderBy('created', 'desc')
                ->first();

            $status = "Risco de incêndio para hoje: {$rcm->getRiskTodayEmoji()} {$rcm->hoje}";

            $data = [
                'chat_id' => env('PR_PROJECT_TELEGRAM_CHANNEL'),
                'text' => $status,
                'message_thread_id' => env('PR_PROJECT_TELEGRAM_CHANNEL_3')
            ];

            file_get_contents("https://api.telegram.org/bot{$apiToken}/sendMessage?".http_build_query($data));
        }

        $rcm = RCM::where('dico', env('PS_PROJECT_TELEGRAM_CHANNEL2_DICOS'))
            ->limit(1)
            ->orderBy('created', 'desc')
            ->first();

        $status = "Risco de incêndio para hoje: {$rcm->getRiskTodayEmoji()} {$rcm->hoje}";

        $data = [
            'chat_id' => env('PR_PROJECT_TELEGRAM_CHANNEL2'),
            'text' => $status,
        ];

        file_get_contents("https://api.telegram.org/bot{$apiToken}/sendMessage?".http_build_query($data));

    }
}

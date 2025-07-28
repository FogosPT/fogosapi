<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\RCM;
use App\Models\WeatherWarning;
use Illuminate\Support\Facades\Log;
use PhpImap\Mailbox;
use voku\helper\UTF8;

class SendWeatherWarningToTelegram extends Job
{

    public $weatherWarning;
    public $telegramToken;
    public $telegramChannel;
    public $telegramChannelThreadId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(WeatherWarning $weatherWarning, $telegramToken, $telegramChannel, $telegramChannelThreadId = null)
    {
        $this->weatherWarning = $weatherWarning;
        $this->telegramToken = $telegramToken;
        $this->telegramChannel = $telegramChannel;
        $this->telegramChannelThreadId = $telegramChannelThreadId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $apiToken = $this->telegramToken;

        $status = "⚠️ Novo Aviso IPMA ⚠️
            - {$this->weatherWarning->getLevelPT()}
            - {$this->weatherWarning->type}:
            {$this->weatherWarning->text}

            - Início: {$this->weatherWarning->startTime->format('d-m-Y H:i')}
            - Fim: {$this->weatherWarning->endTime->format('d-m-Y H:i')}
        ";

       $data = [
           'chat_id' => $this->telegramChannel,
           'text' => $status,
       ];

        if($this->telegramChannelThreadId){
            $data['message_thread_id'] = $this->telegramChannelThreadId;
        }

        file_get_contents("https://api.telegram.org/bot{$apiToken}/sendMessage?".http_build_query($data));
    }
}

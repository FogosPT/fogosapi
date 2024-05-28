<?php

namespace App\Console\Commands;

use App\Models\Warning;
use App\Tools\BlueskyTool;
use App\Tools\FacebookTool;
use App\Tools\NotificationTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Illuminate\Console\Command;

class SaveWarningAndSendNotificationAndSocial extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fogospt:send-warning {status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $status = $this->argument('status');

        $warning = new Warning();
        $warning->text = $status;
        $warning->save();

        NotificationTool::sendWarningNotification($status);

        $text = "ALERTA: \r\n".$status;
        TwitterTool::tweet($text);
        TelegramTool::publish($text);
        BlueskyTool::publish($text);

        $message = 'ALERTA: %0A'.$status;
        FacebookTool::publish($message);
    }
}

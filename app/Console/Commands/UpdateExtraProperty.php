<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Tools\NotificationTool;
use Illuminate\Console\Command;

class UpdateExtraProperty extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fogospt:update-extra {id} {status}';

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
        $id = $this->argument('id');

        $incident = Incident::where('id', $id)->get()[0];

        $incident->extra = $status;
        $incident->save();

        NotificationTool::send($status, $incident->location, $incident->id);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Incident;
use Illuminate\Console\Command;

class FixFMA extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fogospt:fix-fma';

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
        $incidents = Incident::where('isFMA', 'exists', false)->get();

        foreach ($incidents as $incident) {
            $isFMA = in_array($incident['naturezaCode'], Incident::NATUREZA_CODE_FMA);

            $incident->isFMA = $isFMA;
            $incident->save();
        }
    }
}

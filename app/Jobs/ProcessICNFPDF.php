<?php

namespace App\Jobs;

use App\Models\Incident;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\PdfToText\Pdf;

class ProcessICNFPDF extends Job  implements ShouldQueue
{
    public $incident;
    public $url;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Incident $incident, $url)
    {
        $this->incident = $incident;
        $this->url = $url;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        return;


// Initialize the cURL session
        $ch = curl_init($this->url);

// Inintialize directory name where
// file will be save
        $dir = './';

// Use basename() function to return
// the base name of file
        $file_name = basename('asdasdasd.pdf');

// Save file into file location
        $save_file_loc = $dir . $file_name;

// Open file
        $fp = fopen($save_file_loc, 'wb');

// It set an option for a cURL transfer
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);

// Perform a cURL session
        curl_exec($ch);

// Closes a cURL session and frees all resources
        curl_close($ch);

// Close file
        fclose($fp);

        $text = Pdf::getText('asdasdasd.pdf');

        $i = 0;
        foreach(preg_split("/((\r?\n)|(\r\n?))/", $text) as $line){
            echo $i . '=>' . $line . PHP_EOL;

           // if($i===36){
           //     $alertFrom = $line;
            //} elseif( $i === )


            $i++;


        }
    }
}

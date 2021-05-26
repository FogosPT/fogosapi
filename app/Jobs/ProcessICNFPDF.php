<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\HashTagTool;
use App\Tools\TwitterTool;
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
        $ch = curl_init($this->url);
        $dir = './';

        $file_name = basename($this->incident->id . '.pdf');

        $save_file_loc = $dir . $file_name;

        $fp = fopen($save_file_loc, 'wb');

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        $text = Pdf::getText($this->incident->id . '.pdf');

        $i = 0;
        $textArr = preg_split("/((\r?\n)|(\r\n?))/", $text);

        $alertFromExists = false;
        if(isset($this->incident->alertFom) && $this->incident->alertFom){
            $alertFromExists = true;
        }

        $cbvExists = false;
        if(isset($this->incident->cbv) && $this->incident->cbv){
            $cbvExists = true;
        }

        foreach($textArr as $line){
            echo $i . '=>' . $line . PHP_EOL;

            if($line === 'Fonte alerta:' && $i < 100){
                $this->incident->alertFrom = $textArr[$i+1];
            } elseif(preg_match('/CBV/',$line)){
                $this->incident->cbv = explode(',',$line)[1];
            }

            $this->incident->save();
            $i++;
        }

        if(!$cbvExists){
            $hashtag = HashTagTool::getHashTag($this->incident->concelho);
            $status = "ℹ Incêndio na área de intervenção do {$this->incident->cbv} {$hashtag} ℹ";
            $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId);
            $this->incident->lastTweetId = $lastTweetId;
            $this->incident->save();
        }

        if(!$alertFromExists){
            $hashtag = HashTagTool::getHashTag($this->incident->concelho);
            $status = "ℹ Alerta dado por {$this->incident->alertFrom} {$hashtag} ℹ";
            $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId);
            $this->incident->lastTweetId = $lastTweetId;
            $this->incident->save();
        }

        unlink($save_file_loc);
    }
}

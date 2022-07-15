<?php

namespace App\Jobs;

use App\Models\Incident;
use PhpImap\Mailbox;
use voku\helper\UTF8;

class HandleANEPCPositEmail extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $mailbox = new Mailbox(
            '{imap.gmail.com:993/ssl}', // IMAP server and mailbox folder
            env('VOST_EMAIL'), // Username for the before configured mailbox
            env('VOST_EMAIL_PASSWORD'), // Password for the before configured username
            __DIR__, // Directory, where attachments will be saved (optional)
            'UTF-8', // Server encoding (optional)
        );

        try {
            $mailsIds = $mailbox->searchMailbox('UNSEEN');
        } catch(PhpImap\Exceptions\ConnectionException $ex) {
            echo "IMAP connection failed: " . implode(",", $ex->getErrors('all'));
            return;
        }

        if(!$mailsIds) {
            return;
        }

        foreach($mailsIds as $mailId){
            $mail = $mailbox->getMail($mailId);

            if($mail->fromAddress === env('MAIL_ANEPC_FROM')){
                $content = $mail->textHtml;

                $dom = new \DOMDocument();

                @$dom->loadHTML($content);
                $dom->preserveWhiteSpace = false;
                $tables = $dom->getElementsByTagName('table');

                $rows = $tables->item(0)->getElementsByTagName('tr');

                $i = 0;
                $fires = array();

                foreach ($rows as $row) {
                    if($i !== 0){
                        $cols = $row->getElementsByTagName('td');

                        $j = 0;
                        $fire = [];
                        foreach($cols as $col){

                            switch ($j){
                                case 0:
                                    $fire['id'] = $col->nodeValue;
                                    break;
                                case 11:
                                    $fire['heliFight'] = (int)$col->nodeValue;
                                    break;
                                case 12:
                                    $fire['planeFight'] = (int)$col->nodeValue;
                                    break;
                                case 13:
                                    $fire['heliCoord'] = (int)$col->nodeValue;
                                    break;
                                case 15:
                                    $fire['cos'] = UTF8::fix_utf8($col->nodeValue);
                                    break;
                                case 16:
                                    $fire['pco'] = UTF8::fix_utf8($col->nodeValue);
                                    break;
                            }

                            $j++;
                        }
                        $fires[] = $fire;
                    }
                    $i++;
                }
            }

            foreach($fires as $fire){
                $incident = Incident::where('id', $fire['id'])->get()[0];

                $incident->pco = $fire['pco'];
                $incident->cos = $fire['cos'];
                $incident->heliCoord = $fire['heliCoord'];
                $incident->planeFight = $fire['planeFight'];
                $incident->heliFight = $fire['heliFight'];
                $incident->anepcDirectUpdate = true;
                $incident->important = true;
                $incident->save();
            }
        }
    }
}

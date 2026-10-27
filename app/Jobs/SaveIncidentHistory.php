<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\IncidentHistory;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\LiveActivityTool;
use App\Tools\NotificationTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveIncidentHistory extends Job
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $incident;

    /**
     * Create a new job instance.
     */
    public function __construct(Incident $incident)
    {
        $this->incident = $incident;
    }

    private function saveNewIncidentHistory()
    {
        $incidentHistory = new IncidentHistory();
        $incidentHistory->incidentId = $this->incident->id;
        $incidentHistory->sharepointId = $this->incident->sharepointId;
        $incidentHistory->aerial = $this->incident->aerial;
        $incidentHistory->terrain = $this->incident->terrain;
        $incidentHistory->location = $this->incident->location;
        $incidentHistory->man = $this->incident->man;
        // Tracked so POSIT / COS changes can be detected against the last
        // snapshot ('' rather than null so the key is always stored).
        $incidentHistory->extra = (string) ($this->incident->extra ?? '');
        $incidentHistory->cos = (string) ($this->incident->cos ?? '');
        $incidentHistory->save();
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $this->updateIncident();

        $last = IncidentHistory::whereFireId($this->incident->id)
            ->orderBy('created', 'desc')
            ->first();

        if (!$last) {
            $this->saveNewIncidentHistory();

            return;
        }

        $hashTag = HashTagTool::getHashTag($this->incident->concelho);
        $date = date('H:i');

        $domain = env('SOCIAL_LINK_DOMAIN');

        $meansChanged = (int) $this->incident->man !== (int) $last['man']
            || (int) $this->incident->terrain !== (int) $last['terrain']
            || (int) $this->incident->aerial !== (int) $last['aerial'];
        $positChanged = $this->fieldChanged($last, 'extra');
        $cosChanged = $this->fieldChanged($last, 'cos');
        // Snapshots written before POSIT/COS were tracked: store a baseline
        // quietly instead of announcing every incident's current POSIT.
        $missingBaseline = !$this->tracksField($last, 'extra') || !$this->tracksField($last, 'cos');

        if ($meansChanged || $positChanged || $cosChanged || $missingBaseline) {
            // Record first, so a failing channel below cannot make the next
            // run announce the same change again.
            $this->saveNewIncidentHistory();
        }

        if ($positChanged) {
            NotificationTool::sendNewPOSITNotification($this->incident);

            $status = "ℹ🔥{$date} - {$this->incident->location} - Novo ponto de situação: {$this->incident->extra} - https://{$domain}/fogo/{$this->incident->sadoId} {$hashTag} #FogosPT 🔥ℹ";

            $this->publish($status);
        }

        if ($cosChanged) {
            NotificationTool::sendNewCosNotification($this->incident);

            $status = "ℹ🔥{$date} - {$this->incident->location} - Novo Comandante de Operações de Socorro: {$this->incident->cos} - https://{$domain}/fogo/{$this->incident->sadoId} {$hashTag} #FogosPT 🔥ℹ";

            $this->publish($status);
        }

        if ($meansChanged) {
            LiveActivityTool::pushUpdate($this->incident);
        }

        if ($this->incident->man >= env('BIG_INCIDENT_MAN') && !$this->incident['notifyBig']) {
            if($this->incident->isFire){
                $this->incident->notifyBig = true;
                $this->incident->save();

                $date = date('H:i');

                $notification = "ℹ🚨 {$this->incident->location} - Grande mobilização de meios:  👩‍🚒 {$this->incident->man} 🚒 {$this->incident->terrain} 🚁 {$this->incident->aerial} 🚨ℹ";

                NotificationTool::sendImportant($notification, $this->incident->id);

                $status = "ℹ🚨 {$date} - {$this->incident->location} - Grande mobilização de meios:\r\n 👩‍🚒 {$this->incident->man}\r\n 🚒 {$this->incident->terrain}\r\n 🚁 {$this->incident->aerial}\r\n https://{$domain}/fogo/{$this->incident->id} {$hashTag} #FogosPT 🚨ℹ";

                $lastTweetId = TwitterTool::tweet($status);

                TwitterTool::retweetVost($lastTweetId);

                // Keep the thread when the tweet failed (tweet() returns null).
                if ($lastTweetId) {
                    $this->incident->lastTweetId = $lastTweetId;
                }
                $this->incident->save();

                $statusf = "ℹ🚨 {$date} - {$this->incident->location} - Grande mobilização de meios:%0A  👩‍🚒 {$this->incident->man}%0A 🚒 {$this->incident->terrain}%0A 🚁 {$this->incident->aerial}%0A https://{$domain}/fogo/{$this->incident->id} {$hashTag} #FogosPT 🚨ℹ";
                FacebookTool::publish($statusf);

                TelegramTool::publish($status);
            }
        }
    }

    /**
     * Tweet in the incident thread, then Facebook and Telegram.
     */
    private function publish(string $status): void
    {
        $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId);

        // Keep the thread when the tweet failed (tweet() returns null).
        if ($lastTweetId) {
            $this->incident->lastTweetId = $lastTweetId;
            $this->incident->save();
        }

        FacebookTool::publish($status);
        TelegramTool::publish($status);
    }

    private function tracksField(IncidentHistory $last, string $field): bool
    {
        return array_key_exists($field, $last->getAttributes());
    }

    /**
     * A new, non-empty value that differs from the last snapshot. Clearing
     * the field is not announced.
     */
    private function fieldChanged(IncidentHistory $last, string $field): bool
    {
        if (!$this->tracksField($last, $field)) {
            return false;
        }

        $current = trim((string) ($this->incident->{$field} ?? ''));

        return $current !== '' && $current !== trim((string) $last[$field]);
    }

    private function updateIncident()
    {
        $this->incident = Incident::whereFireId($this->incident->id)->firstOrFail();
    }
}

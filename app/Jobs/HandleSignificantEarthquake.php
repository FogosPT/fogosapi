<?php

namespace App\Jobs;

use App\Models\Earthquake;
use App\Support\EarthquakeAlert;
use App\Tools\DiscordTool;
use Carbon\Carbon;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Announces a significant earthquake (and later revisions of it) on the
 * sismos.pt Discord channel. Dispatched by ProcessSeismicSource after every
 * merge that could change the answer of EarthquakeAlert::pending().
 */
class HandleSignificantEarthquake extends Job
{
    public function __construct(public string $earthquakeId) {}

    public function handle(): void
    {
        if (!filter_var(env('SEISMIC_NOTIFICATIONS_ENABLE', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $quake = Earthquake::find($this->earthquakeId);
        if (!$quake || ($kind = EarthquakeAlert::pending($quake)) === null) {
            return;
        }

        // Claim the alert atomically: two sources merging the same event in
        // parallel workers must not both post it.
        $claim = $kind === 'new'
            ? ['notifiedAt' => null]
            : ['notifiedMagnitude' => $quake->notifiedMagnitude, 'notifiedFelt' => $quake->notifiedFelt];
        $claimed = Earthquake::raw(fn ($c) => $c->updateOne(
            ['_id' => new ObjectId($quake->id)] + $claim,
            ['$set' => [
                'notifiedAt' => $kind === 'new' ? new UTCDateTime(Carbon::now()) : new UTCDateTime($quake->notifiedAt),
                'notifiedMagnitude' => $quake->magnitude,
                'notifiedFelt' => (bool) $quake->felt,
            ]]
        ));
        if ($claimed->getModifiedCount() !== 1) {
            return;
        }

        DiscordTool::postSeismic(EarthquakeAlert::message($quake, $kind));
    }
}

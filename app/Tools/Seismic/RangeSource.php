<?php

namespace App\Tools\Seismic;

use Carbon\Carbon;

/**
 * A catalogue that can be queried for an arbitrary time range (used by
 * `seismic:backfill`). PAGE_LIMIT is the most events one request returns; a
 * chunk whose raw response is full is split in two and fetched again.
 */
interface RangeSource
{
    /**
     * @return SeismicEvent[]
     */
    public function fetchRange(Carbon $from, Carbon $to, ?float $minMagnitude = null): array;

    public function pageLimit(): int;

    /** Records in the last response before filtering (see SeismicSource::rawCount()). */
    public function rawCount(): int;
}

<?php

namespace App\Support;

use App\Models\Earthquake;
use App\Tools\Seismic\SeismicEvent;
use App\Tools\Seismic\SeismicSources;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Folds the reports of every source into one Earthquake per real event.
 *
 * A report matches an existing earthquake when it carries the same source id,
 * or when its origin is within MAX_SECONDS / MAX_KM of it (doubled for M5+,
 * where catalogues disagree more). The preferred solution follows
 * SeismicSources::PRECEDENCE_PT inside Portuguese regions and
 * PRECEDENCE_OTHER elsewhere; magnitude comes from the first source in that
 * order that has a final (non-provisional) value.
 */
class EarthquakeMerger
{
    public const MAX_SECONDS = 10;
    public const MAX_KM = 30;

    /** Same-source reports this close in time are revisions, not new events. */
    private const REVISION_SECONDS = 2;

    /**
     * @return array{0: Earthquake, 1: bool} the earthquake and whether it was created
     */
    public function upsert(SeismicEvent $event): array
    {
        $quake = Earthquake::where("sources.{$event->source}.id", $event->sourceId)->first()
            ?? $this->findNearby($event);

        $created = $quake === null;
        if ($created) {
            $quake = new Earthquake();
            $quake->firstSeenAt = Carbon::now();
            $quake->sources = [];
        }

        $sources = $quake->sources ?? [];
        $previous = $sources[$event->source] ?? null;
        $entry = $event->toSourceArray();
        $entry['firstSeenAt'] = $previous['firstSeenAt'] ?? Carbon::now()->utc()->format('Y-m-d\TH:i:s\Z');
        $sources[$event->source] = $entry;
        $quake->sources = $sources;

        $this->recompute($quake);
        $quake->save();

        return [$quake, $created];
    }

    private function findNearby(SeismicEvent $event): ?Earthquake
    {
        $window = self::MAX_SECONDS * 2;
        $candidates = Earthquake::where('time', '>=', $event->time->copy()->subSeconds($window))
            ->where('time', '<=', $event->time->copy()->addSeconds($window))
            ->get();

        $best = null;
        $bestScore = INF;
        foreach ($candidates as $candidate) {
            $own = $candidate->sources[$event->source] ?? null;
            if ($own !== null && abs(Carbon::parse($own['time'])->diffInSeconds($event->time, false)) > self::REVISION_SECONDS) {
                // Already holds a different event from this source.
                continue;
            }

            $large = max((float) $candidate->magnitude, (float) $event->magnitude) >= 5;
            $maxSeconds = self::MAX_SECONDS * ($large ? 2 : 1);
            $maxKm = self::MAX_KM * ($large ? 2 : 1);

            $dt = abs($candidate->time->diffInMilliseconds($event->time, false)) / 1000;
            $km = self::distanceKm($candidate->lat, $candidate->lon, $event->lat, $event->lon);
            if ($dt > $maxSeconds || $km > $maxKm) {
                continue;
            }

            $score = $dt / $maxSeconds + $km / $maxKm;
            if ($score < $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function recompute(Earthquake $quake): void
    {
        $sources = $quake->sources;

        // The best-ranked solution under the PT order decides which precedence applies.
        $lead = $sources[current(array_filter(SeismicSources::PRECEDENCE_PT, fn ($s) => isset($sources[$s])))];
        $region = SeismicRegion::classify((float) $lead['lat'], (float) $lead['lon']);
        $order = SeismicRegion::isPortuguese($region) ? SeismicSources::PRECEDENCE_PT : SeismicSources::PRECEDENCE_OTHER;
        $present = array_values(array_filter($order, fn ($s) => isset($sources[$s])));

        $preferred = $present[0];
        $origin = $sources[$preferred];

        $magnitudeSource = null;
        foreach ($present as $s) {
            if ($sources[$s]['magnitude'] !== null) {
                $magnitudeSource = $s;

                break;
            }
        }

        $quake->preferredSource = $preferred;
        $quake->time = Carbon::parse($origin['time']);
        $quake->lat = (float) $origin['lat'];
        $quake->lon = (float) $origin['lon'];
        $quake->location = ['type' => 'Point', 'coordinates' => [(float) $origin['lon'], (float) $origin['lat']]];
        $quake->depth = $origin['depth'] ?? $this->firstValue($sources, $present, 'depth');
        $quake->region = SeismicRegion::classify($quake->lat, $quake->lon);
        // IPMA names places in Portuguese ("NE Bragança") even outside PT.
        $quake->place = $sources['ipma']['place'] ?? $this->firstValue($sources, $present, 'place');

        $quake->magnitudeSource = $magnitudeSource;
        $quake->magnitude = $magnitudeSource ? (float) $sources[$magnitudeSource]['magnitude'] : null;
        $quake->magType = $magnitudeSource ? $sources[$magnitudeSource]['magType'] : null;
        $quake->provisional = $magnitudeSource === null;
        $quake->energyJ = $quake->magnitude !== null ? 10 ** (1.5 * $quake->magnitude + 4.8) : null;

        // Felt information is IPMA's (macroseismic survey) with USGS DYFI as backup.
        $ipma = $sources['ipma'] ?? null;
        $quake->felt = collect($sources)->contains(fn ($s) => ($s['felt'] ?? null) === true);
        $quake->intensity = $ipma['intensity'] ?? null;
        $quake->intensityMax = self::intensityToNumber($quake->intensity);
        $quake->local = $ipma['local'] ?? null;
        $quake->shakemapUrl = $ipma['extra']['shakemapref'] ?? null;

        $quake->slug = self::slug($quake);
    }

    private function firstValue(array $sources, array $present, string $field): mixed
    {
        foreach ($present as $s) {
            if (($sources[$s][$field] ?? null) !== null) {
                return $sources[$s][$field];
            }
        }

        return null;
    }

    public static function slug(Earthquake $quake): string
    {
        $mag = $quake->magnitude !== null ? 'm'.str_replace('.', '-', number_format($quake->magnitude, 1)) : 'm-por-rever';

        return Str::slug(implode(' ', array_filter([
            $quake->time->copy()->utc()->format('Y-m-d Hi'),
            $mag,
            $quake->place ? Str::limit($quake->place, 40, '') : null,
        ])));
    }

    /**
     * "III/IV" → 3.5, "V" → 5. Null when absent or unparseable.
     */
    public static function intensityToNumber(?string $intensity): ?float
    {
        if ($intensity === null || trim($intensity) === '') {
            return null;
        }

        $roman = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6, 'VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10, 'XI' => 11, 'XII' => 12];
        $values = [];
        foreach (preg_split('/[\/-]/', strtoupper(trim($intensity))) as $part) {
            $part = trim($part);
            if (isset($roman[$part])) {
                $values[] = $roman[$part];
            } elseif (is_numeric($part)) {
                $values[] = (float) $part;
            }
        }

        return $values ? array_sum($values) / count($values) : null;
    }

    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}

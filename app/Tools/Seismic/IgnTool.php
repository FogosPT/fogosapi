<?php

namespace App\Tools\Seismic;

use Carbon\Carbon;

/**
 * Instituto Geográfico Nacional (Spain) GeoRSS, last ten days:
 * https://www.ign.es/ign/RssTools/sismologia.xml
 *
 * No FDSN service; magnitude and place live inside the Spanish description.
 * Useful for the Gulf of Cádiz, Cabo de São Vicente and the border regions.
 */
class IgnTool extends SeismicSource
{
    public const URL = 'https://www.ign.es/ign/RssTools/sismologia.xml';

    public function name(): string
    {
        return 'ign';
    }

    public function fetchRecent(int $days = 10): array
    {
        return $this->fetchAndParse(self::URL);
    }

    public function parse(string $body): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        if ($xml === false || !isset($xml->channel)) {
            throw InvalidSeismicPayload::from($this->name(), $body);
        }

        $events = [];
        foreach ($xml->channel->item as $item) {
            $geo = $item->children('http://www.w3.org/2003/01/geo/wgs84_pos#');
            $description = (string) $item->description;

            if (!preg_match('/magnitud\s+([\d.]+)\s+en\s+(.+?)\s+en la fecha\s+(\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2}:\d{2})/u', $description, $m)) {
                continue;
            }

            $link = (string) $item->link;
            $evid = preg_match('/evid=([\w]+)/', $link, $idm) ? $idm[1] : md5($link);

            // The IGN catalogue publishes origin times in UTC.
            $events[] = new SeismicEvent(
                source: $this->name(),
                sourceId: $evid,
                time: Carbon::createFromFormat('d/m/Y G:i:s', $m[3], 'UTC'),
                lat: (float) $geo->lat,
                lon: (float) $geo->long,
                depth: null,
                magnitude: (float) $m[1],
                magType: null,
                place: trim($m[2]),
                url: $link,
            );
        }

        return $events;
    }
}

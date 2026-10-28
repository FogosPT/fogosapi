<?php

namespace App\Tools\Seismic;

use Carbon\Carbon;
use GuzzleHttp\Client;

/**
 * Base class for the seismic catalogues. Each source fetches its own feed and
 * parses it into SeismicEvent objects; parse() is public so tests can feed it
 * recorded responses.
 */
abstract class SeismicSource
{
    /**
     * Area of interest: mainland Portugal and its margin, Madeira and the
     * Azores, plus the Gulf of Cádiz and the Gorringe bank.
     */
    public const BBOX = ['minLat' => 29.0, 'maxLat' => 44.5, 'minLon' => -33.0, 'maxLon' => -5.5];

    /** Records in the last parsed response, before any filtering. */
    protected int $rawCount = 0;

    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    abstract public function name(): string;

    /**
     * How many records the last response held before parse() dropped any
     * (explosions, events without magnitude, outside the area). Pagination
     * must be judged on this, not on the events returned.
     */
    public function rawCount(): int
    {
        return $this->rawCount;
    }

    /**
     * @return SeismicEvent[]
     *
     * @throws InvalidSeismicPayload when the body is not the source's format
     */
    abstract public function parse(string $body): array;

    /**
     * Events from the last $days days (sources with a fixed window ignore it).
     *
     * @return SeismicEvent[]
     */
    abstract public function fetchRecent(int $days = 3): array;

    /**
     * @return SeismicEvent[]
     */
    protected function fetchAndParse(string $url): array
    {
        return array_values(array_filter(
            $this->parse($this->get($url)),
            fn (SeismicEvent $e) => self::inBbox($e->lat, $e->lon)
        ));
    }

    protected function get(string $url): string
    {
        $res = $this->client->request('GET', $url, [
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0 (+https://sismos.pt)',
            ],
            'connect_timeout' => 10,
            'timeout' => 30,
        ]);

        return $res->getBody()->getContents();
    }

    public static function inBbox(float $lat, float $lon): bool
    {
        return $lat >= self::BBOX['minLat'] && $lat <= self::BBOX['maxLat']
            && $lon >= self::BBOX['minLon'] && $lon <= self::BBOX['maxLon'];
    }

    protected static function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected static function fdsnTime(Carbon $time): string
    {
        return $time->copy()->utc()->format('Y-m-d\TH:i:s');
    }
}

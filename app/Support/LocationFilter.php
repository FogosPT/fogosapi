<?php

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The `district`, `concelho` and `freguesia` query filters shared by the read
 * endpoints. Each takes one or more names; a value matches when it is any of
 * them, at any level (OR), so a client sends the most specific level of each
 * branch it wants (`district=Porto&concelho=Guimarães`).
 *
 * Lists are separated by `|`. A comma also separates, unless the whole value
 * is a known name — parish names contain commas ("União das freguesias de
 * Belazaima do Chão, Castanheira do Vouga e Agadão").
 *
 * Names are validated against IncidentLocations (422 `invalid_{level}`) and
 * matched in every stored spelling.
 */
final class LocationFilter
{
    /**
     * @param array<string, array<int, string>> $values level => names as sent
     */
    private function __construct(private readonly array $values)
    {
    }

    /**
     * @param array<string, array<int, string>> $aliases extra query keys per level (e.g. legacy `distrito`)
     * @param bool $validate false keeps unknown names (they just match nothing), for legacy endpoints
     */
    public static function fromRequest(Request $request, array $aliases = [], bool $validate = true): self
    {
        $values = [];
        $invalid = [];

        foreach (IncidentLocations::LEVELS as $level) {
            $names = [];
            foreach (array_merge([$level], $aliases[$level] ?? []) as $param) {
                $raw = $request->query($param);
                if (is_string($raw) && trim($raw) !== '') {
                    array_push($names, ...self::parse($level, $raw));
                }
            }

            $names = array_values(array_unique($names));
            foreach ($names as $name) {
                if ($validate && !IncidentLocations::isKnown($level, $name)) {
                    $invalid[$level][] = $name;
                }
            }
            $values[$level] = $names;
        }

        if ($invalid) {
            $level = array_key_first($invalid);
            throw new HttpResponseException(new JsonResponse([
                'success' => false,
                'error' => 'invalid_' . $level,
                'message' => 'Unknown ' . $level . ': ' . implode(', ', $invalid[$level]),
                'invalid' => $invalid,
            ], 422));
        }

        return new self($values);
    }

    /**
     * @return array<int, string>
     */
    public static function parse(string $level, string $raw): array
    {
        $raw = trim($raw);

        if (str_contains($raw, '|')) {
            $parts = explode('|', $raw);
        } elseif (str_contains($raw, ',') && !IncidentLocations::isKnownStrict($level, $raw)) {
            $parts = explode(',', $raw);
        } else {
            $parts = [$raw];
        }

        return array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }

    public function isEmpty(): bool
    {
        return !array_filter($this->values);
    }

    /** @return array<int, string> */
    public function names(string $level): array
    {
        return $this->values[$level] ?? [];
    }

    /**
     * Stored values $level must be in: each name as sent, with the casing the
     * ingestion jobs store ("Vila Nova De Gaia") and every spelling seen on
     * incidents.
     *
     * @return array<int, string>
     */
    public function matchValues(string $level): array
    {
        $out = [];
        foreach ($this->names($level) as $name) {
            $out[] = $name;
            $out[] = Concelho::normalize($name);
            array_push($out, ...IncidentLocations::spellings($level, $name));
        }

        return array_values(array_unique($out));
    }

    /**
     * Restrict an incident query to the selected locations (no-op when empty).
     *
     * @param array<string, string> $fields level => document field
     */
    public function apply($query, array $fields = ['district' => 'district', 'concelho' => 'concelho', 'freguesia' => 'freguesia'])
    {
        if ($this->isEmpty()) {
            return $query;
        }

        return $query->where(function ($q) use ($fields) {
            foreach ($fields as $level => $field) {
                if ($this->names($level)) {
                    $q->orWhereIn($field, $this->matchValues($level));
                }
            }
        });
    }
}

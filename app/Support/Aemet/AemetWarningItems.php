<?php

namespace App\Support\Aemet;

use Carbon\Carbon;

/**
 * Turns the CAP messages of one AEMET snapshot into items shaped like the IPMA warnings
 * feed, so `WeatherWarningSync::sync()` can reconcile them.
 *
 * Each item carries the IPMA properties (idAreaAviso, awarenessTypeName, awarenessLevelID,
 * startTime, endTime, text, reportDate — times as ISO 8601 with offset) plus areaName,
 * phenomenon, parameter, probability, identifiers and en.
 */
final class AemetWarningItems
{
    /** AEMET phenomenon code → IPMA `awarenessTypeName`. Others keep the Spanish name. */
    public const TYPES = [
        'VI' => 'Vento',
        'PR' => 'Precipitação',
        'NE' => 'Neve',
        'TO' => 'Trovoada',
        'AT' => 'Tempo Quente',
        'BT' => 'Tempo Frio',
        'CO' => 'Agitação Marítima',
        'NI' => 'Nevoeiro',
    ];

    public const LEVELS = [
        'amarillo' => 'yellow',
        'naranja' => 'orange',
        'rojo' => 'red',
    ];

    /**
     * @param CapMessage[] $messages
     *
     * @return object[]
     */
    public static function fromMessages(array $messages, Carbon $now): array
    {
        $groups = [];
        foreach (self::current($messages, $now) as $message) {
            foreach ($message->areas as $area) {
                $item = self::item($message, $area);
                $groups[$item['idAreaAviso']."\0".$item['awarenessTypeName']."\0".$item['awarenessLevelID']][] = $item;
            }
        }
        ksort($groups, SORT_STRING);

        $out = [];
        foreach ($groups as $group) {
            foreach (self::merge($group) as $item) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Rules 1 and 2: superseded, cancelled, verde and expired messages are dropped.
     *
     * @param CapMessage[] $messages
     *
     * @return CapMessage[]
     */
    private static function current(array $messages, Carbon $now): array
    {
        $superseded = [];
        foreach ($messages as $message) {
            foreach ($message->references as $reference) {
                if ($reference !== $message->identifier) {
                    $superseded[$reference] = true;
                }
            }
        }

        return array_values(array_filter($messages, fn (CapMessage $m) => !isset($superseded[$m->identifier])
            && 'Cancel' !== $m->msgType
            && isset(self::LEVELS[$m->level])
            && !$m->expires->lt($now)));
    }

    /**
     * @param array{code: string, name: string} $area
     */
    private static function item(CapMessage $message, array $area): array
    {
        return [
            'idAreaAviso' => $area['code'],
            'awarenessTypeName' => self::TYPES[$message->phenomenon] ?? ($message->phenomenonName ?: $message->phenomenon),
            'awarenessLevelID' => self::LEVELS[$message->level],
            'start' => $message->onset,
            'end' => $message->expires,
            'sent' => $message->sent,
            'text' => $message->es['description'],
            'areaName' => $area['name'],
            'phenomenon' => $message->phenomenon,
            'parameter' => $message->es['parameter'],
            'probability' => $message->es['probability'],
            'identifier' => $message->identifier,
            'en' => $message->en,
        ];
    }

    /**
     * Rule 4: within one (zone, type, level) group, items whose windows strictly overlap
     * (transitively) become one item.
     *
     * @return object[]
     */
    private static function merge(array $group): array
    {
        usort($group, fn (array $a, array $b) => [$a['start']->getTimestamp(), $a['end']->getTimestamp(), $a['identifier']]
            <=> [$b['start']->getTimestamp(), $b['end']->getTimestamp(), $b['identifier']]);

        // Sorted by start, a cluster grows while the next start is strictly before its end.
        $clusters = [];
        $end = null;
        foreach ($group as $item) {
            if (null !== $end && $item['start']->getTimestamp() < $end) {
                $clusters[count($clusters) - 1][] = $item;
                $end = max($end, $item['end']->getTimestamp());
            } else {
                $clusters[] = [$item];
                $end = $item['end']->getTimestamp();
            }
        }

        return array_map(self::combine(...), $clusters);
    }

    private static function combine(array $cluster): object
    {
        // Texts and parameters follow the phenomenon-parameter suffix of the identifier
        // (…713201PRP1… before …713201PRP2…), then the full identifier.
        $ordered = $cluster;
        usort($ordered, fn (array $a, array $b) => [self::suffix($a['identifier']), $a['identifier']]
            <=> [self::suffix($b['identifier']), $b['identifier']]);

        $byIdentifier = $cluster;
        usort($byIdentifier, fn (array $a, array $b) => strcmp($a['identifier'], $b['identifier']));
        $first = $byIdentifier[0];

        $identifiers = array_values(array_unique(array_column($cluster, 'identifier')));
        sort($identifiers, SORT_STRING);

        $start = min(array_map(fn (array $i) => $i['start']->getTimestamp(), $cluster));
        $end = max(array_map(fn (array $i) => $i['end']->getTimestamp(), $cluster));
        $sent = max(array_map(fn (array $i) => $i['sent']->getTimestamp(), $cluster));

        return (object) [
            'idAreaAviso' => $first['idAreaAviso'],
            'awarenessTypeName' => $first['awarenessTypeName'],
            'awarenessLevelID' => $first['awarenessLevelID'],
            'startTime' => Carbon::createFromTimestampUTC($start)->toIso8601String(),
            'endTime' => Carbon::createFromTimestampUTC($end)->toIso8601String(),
            'text' => self::distinct(array_column($ordered, 'text'), ' '),
            'reportDate' => Carbon::createFromTimestampUTC($sent)->toIso8601String(),
            'areaName' => $first['areaName'],
            'phenomenon' => $first['phenomenon'],
            'parameter' => self::distinct(array_column($ordered, 'parameter'), '; '),
            'probability' => $first['probability'],
            'identifiers' => $identifiers,
            'en' => $first['en'],
        ];
    }

    /** The last dotted segment of a CAP identifier (zone + phenomenon + parameter + …). */
    private static function suffix(string $identifier): string
    {
        $pos = strrpos($identifier, '.');

        return false === $pos ? $identifier : substr($identifier, $pos + 1);
    }

    /** @param array<int, ?string> $values */
    private static function distinct(array $values, string $glue): ?string
    {
        $values = array_values(array_unique(array_filter($values, fn ($v) => null !== $v && '' !== $v)));

        return [] === $values ? null : implode($glue, $values);
    }
}

<?php

namespace App\Support\Aemet;

use Carbon\Carbon;

/**
 * One AEMET CAP 1.2 message (one file of the `avisos_cap` tar).
 *
 * Each message has two <info> blocks (es-ES and en-GB) with the same areas; the level,
 * phenomenon, times and areas come from the Spanish block. Times are exposed in UTC and
 * polygons as [lon, lat] (CAP writes `lat,lon`).
 */
final class CapMessage
{
    public const NS = 'urn:oasis:names:tc:emergency:cap:1.2';

    public const LEVELS = ['verde', 'amarillo', 'naranja', 'rojo'];

    private const PARAM_LEVEL = 'AEMET-Meteoalerta nivel';
    private const PARAM_PARAMETER = 'AEMET-Meteoalerta parametro';
    private const PARAM_PROBABILITY = 'AEMET-Meteoalerta probabilidad';
    private const EVENT_PHENOMENON = 'AEMET-Meteoalerta fenomeno';
    private const GEOCODE_ZONE = 'AEMET-Meteoalerta zona';

    /**
     * @param string[]                                                                                                       $references identifiers this message replaces
     * @param array{headline: ?string, description: ?string, instruction: ?string, parameter: ?string, probability: ?string} $es
     * @param array{headline: ?string, description: ?string, instruction: ?string, parameter: ?string, probability: ?string} $en
     * @param array<int, array{code: string, name: string, polygons: array<int, array<int, array{0: float, 1: float}>>}>     $areas
     */
    public function __construct(
        public readonly string $identifier,
        public readonly Carbon $sent,
        public readonly string $msgType,
        public readonly array $references,
        public readonly string $level,
        public readonly string $phenomenon,
        public readonly string $phenomenonName,
        public readonly Carbon $onset,
        public readonly Carbon $expires,
        public readonly array $es,
        public readonly array $en,
        public readonly array $areas,
        public readonly string $raw,
    ) {}

    /**
     * @throws \RuntimeException when the XML is not a usable CAP alert
     */
    public static function fromXml(string $xml): self
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $root = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (false === $root || 'alert' !== $root->getName()) {
            throw new \RuntimeException('AEMET CAP: not a CAP alert document');
        }

        $alert = $root->children(self::NS);
        $identifier = self::text($alert->identifier);
        if (null === $identifier) {
            throw new \RuntimeException('AEMET CAP: alert without identifier');
        }

        $infos = [];
        foreach ($alert->info as $info) {
            $language = self::text($info->language);
            if (null !== $language && !isset($infos[$language])) {
                $infos[$language] = $info;
            }
        }

        $es = $infos['es-ES'] ?? null;
        if (null === $es) {
            throw new \RuntimeException("AEMET CAP {$identifier}: no es-ES info block");
        }
        $en = $infos['en-GB'] ?? null;

        $level = self::parameter($es, self::PARAM_LEVEL);
        if (null === $level || !in_array($level, self::LEVELS, true)) {
            throw new \RuntimeException("AEMET CAP {$identifier}: unknown level ".var_export($level, true));
        }

        $phenomenon = self::eventCode($es, self::EVENT_PHENOMENON);
        [$phenomenonCode, $phenomenonName] = array_pad(explode(';', (string) $phenomenon, 2), 2, null);
        if (null === $phenomenonCode || '' === trim($phenomenonCode)) {
            throw new \RuntimeException("AEMET CAP {$identifier}: no phenomenon");
        }

        return new self(
            identifier: $identifier,
            sent: self::time($alert->sent, $identifier, 'sent'),
            msgType: self::text($alert->msgType) ?? 'Alert',
            references: self::references(self::text($alert->references)),
            level: $level,
            phenomenon: trim($phenomenonCode),
            phenomenonName: trim((string) $phenomenonName),
            onset: self::time($es->onset, $identifier, 'onset'),
            expires: self::time($es->expires, $identifier, 'expires'),
            es: self::texts($es),
            en: self::texts($en),
            areas: self::areas($es),
            raw: $xml,
        );
    }

    private static function text(?\SimpleXMLElement $node): ?string
    {
        // A missing child is an empty element (or null), both cast to ''.
        $value = trim((string) $node);

        return '' === $value ? null : $value;
    }

    private static function time(?\SimpleXMLElement $node, string $identifier, string $field): Carbon
    {
        $value = self::text($node);
        if (null === $value) {
            throw new \RuntimeException("AEMET CAP {$identifier}: no {$field}");
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable $e) {
            throw new \RuntimeException("AEMET CAP {$identifier}: invalid {$field} '{$value}'", 0, $e);
        }
    }

    /**
     * `sender,identifier,sent` triples separated by spaces → identifiers.
     *
     * @return string[]
     */
    private static function references(?string $references): array
    {
        if (null === $references) {
            return [];
        }

        $out = [];
        foreach (preg_split('/\s+/', $references) ?: [] as $triple) {
            $parts = explode(',', $triple);
            if (count($parts) >= 2 && '' !== trim($parts[1])) {
                $out[] = trim($parts[1]);
            }
        }

        return array_values(array_unique($out));
    }

    private static function parameter(\SimpleXMLElement $info, string $name): ?string
    {
        foreach ($info->parameter as $parameter) {
            if (self::text($parameter->valueName) === $name) {
                return self::text($parameter->value);
            }
        }

        return null;
    }

    private static function eventCode(\SimpleXMLElement $info, string $name): ?string
    {
        foreach ($info->eventCode as $code) {
            if (self::text($code->valueName) === $name) {
                return self::text($code->value);
            }
        }

        return null;
    }

    /**
     * @return array{headline: ?string, description: ?string, instruction: ?string, parameter: ?string, probability: ?string}
     */
    private static function texts(?\SimpleXMLElement $info): array
    {
        if (null === $info) {
            return ['headline' => null, 'description' => null, 'instruction' => null, 'parameter' => null, 'probability' => null];
        }

        return [
            'headline' => self::text($info->headline),
            'description' => self::text($info->description),
            'instruction' => self::text($info->instruction),
            'parameter' => self::formatParameter(self::parameter($info, self::PARAM_PARAMETER)),
            'probability' => self::parameter($info, self::PARAM_PROBABILITY),
        ];
    }

    /** `RM;Rachas máximas;80 km/h` → `Rachas máximas: 80 km/h`. */
    private static function formatParameter(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $parts = array_map('trim', explode(';', $value));
        if (count($parts) >= 3) {
            array_shift($parts);
            $name = array_shift($parts);

            return $name.': '.implode(';', $parts);
        }

        return end($parts) ?: null;
    }

    /**
     * @return array<int, array{code: string, name: string, polygons: array<int, array<int, array{0: float, 1: float}>>}>
     */
    private static function areas(\SimpleXMLElement $info): array
    {
        $areas = [];
        foreach ($info->area as $area) {
            $code = null;
            foreach ($area->geocode as $geocode) {
                if (self::text($geocode->valueName) === self::GEOCODE_ZONE) {
                    $code = self::text($geocode->value);

                    break;
                }
            }
            if (null === $code) {
                continue;
            }

            $polygons = [];
            foreach ($area->polygon as $polygon) {
                $ring = self::ring((string) $polygon);
                if (count($ring) >= 3) {
                    $polygons[] = $ring;
                }
            }

            $areas[] = [
                'code' => $code,
                'name' => self::text($area->areaDesc) ?? $code,
                'polygons' => $polygons,
            ];
        }

        return $areas;
    }

    /**
     * `lat,lon lat,lon …` → [[lon, lat], …].
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private static function ring(string $polygon): array
    {
        $ring = [];
        foreach (preg_split('/\s+/', trim($polygon)) ?: [] as $point) {
            $coords = explode(',', $point);
            if (2 !== count($coords) || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
                continue;
            }
            $ring[] = [(float) $coords[1], (float) $coords[0]];
        }

        return $ring;
    }
}

<?php

namespace App\Tools\Seismic;

/**
 * The source answered, but not with its catalogue format (an HTML error page
 * behind a 200, truncated JSON…). Distinct from a valid, empty catalogue so
 * that the source is reported as failing rather than healthy.
 */
class InvalidSeismicPayload extends \RuntimeException
{
    public static function from(string $source, string $body): self
    {
        return new self("{$source}: unexpected payload (".strlen($body).' bytes): '.mb_substr(trim(strip_tags($body)), 0, 80));
    }
}

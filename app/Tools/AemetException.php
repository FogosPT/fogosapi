<?php

namespace App\Tools;

/**
 * Thrown by `AemetTool` on any failure talking to the AEMET OpenData API:
 * network errors, an empty/invalid body, HTTP != 200 on either step of the
 * two-step request, or `estado` != 200 in the step-1 envelope. The message
 * is the reason text (AEMET's own `descripcion` when there is one).
 */
class AemetException extends \RuntimeException
{
    public function __construct(string $reason, public readonly bool $unauthorized = false)
    {
        parent::__construct($reason);
    }
}

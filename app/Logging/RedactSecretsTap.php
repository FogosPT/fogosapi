<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/** Logging channel `tap` that adds the RedactSecrets processor. */
class RedactSecretsTap
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new RedactSecrets());
    }
}

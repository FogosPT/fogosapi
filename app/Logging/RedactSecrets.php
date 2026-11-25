<?php

namespace App\Logging;

use App\Support\SecretRedactor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/** Masks secret env values in every record (message, context, extra). */
class RedactSecrets implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: SecretRedactor::redact($record->message),
            context: SecretRedactor::redactValue($record->context),
            extra: SecretRedactor::redactValue($record->extra),
        );
    }
}

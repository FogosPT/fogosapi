<?php

namespace App\Support;

use Sentry\Breadcrumb;
use Sentry\Event;

class SentryScrubber
{
    /**
     * Header names (lower-case) holding API keys. Sentry only scrubs
     * Authorization/Cookie/X-Forwarded-For by default.
     */
    private const SECRET_HEADERS = ['key'];

    public static function beforeSend(Event $event): ?Event
    {
        $request = $event->getRequest();

        if (!empty($request['headers'])) {
            foreach ($request['headers'] as $name => $value) {
                if (in_array(strtolower((string) $name), self::SECRET_HEADERS, true)) {
                    $request['headers'][$name] = '[Filtered]';
                }
            }
        }
        foreach (['url', 'query_string'] as $field) {
            if (isset($request[$field]) && is_string($request[$field])) {
                $request[$field] = SecretRedactor::redact($request[$field]);
            }
        }
        if ($request !== []) {
            $event->setRequest($request);
        }

        // Secret env values (bot tokens in Telegram URLs, access_token in Facebook
        // queries…) travel in exception messages and HTTP breadcrumbs.
        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(SecretRedactor::redact($exception->getValue()));
        }

        if ($event->getMessage() !== null) {
            $formatted = $event->getMessageFormatted();
            $event->setMessage(
                SecretRedactor::redact($event->getMessage()),
                SecretRedactor::redactValue($event->getMessageParams()),
                $formatted !== null ? SecretRedactor::redact($formatted) : null
            );
        }

        $event->setBreadcrumb(array_map([self::class, 'scrubBreadcrumb'], $event->getBreadcrumbs()));
        $event->setExtra(SecretRedactor::redactValue($event->getExtra()));

        return $event;
    }

    private static function scrubBreadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        if ($breadcrumb->getMessage() !== null) {
            $breadcrumb = $breadcrumb->withMessage(SecretRedactor::redact($breadcrumb->getMessage()));
        }
        foreach ($breadcrumb->getMetadata() as $name => $value) {
            $breadcrumb = $breadcrumb->withMetadata($name, SecretRedactor::redactValue($value));
        }

        return $breadcrumb;
    }
}

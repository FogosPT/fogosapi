<?php

namespace App\Support;

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
            $event->setRequest($request);
        }

        return $event;
    }
}

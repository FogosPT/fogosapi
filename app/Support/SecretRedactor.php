<?php

namespace App\Support;

/**
 * Masks the values of secret environment variables (tokens, passwords, keys,
 * webhook secrets) in text before it reaches the logs or Sentry. Most
 * integrations read their credentials with env() and put them in request URLs
 * (Telegram bot token in the path, Facebook access_token in the query), which
 * Guzzle exception messages then carry in full.
 *
 * Secrets are found by variable name, so a new *_TOKEN / *_KEY / *_PASSWORD is
 * covered without touching this class.
 */
class SecretRedactor
{
    public const MASK = '[redacted]';

    private const NAME_PATTERN = '/(TOKEN|SECRET|PASSWORD|_PASS$|_KEY$|ACCESS_CODE|DSN$|WEBHOOK_URL$)/';

    /** Shorter values (flags, ports, "null") would mask unrelated text. */
    private const MIN_LENGTH = 8;

    private const MAX_DEPTH = 6;

    /** @var list<string>|null longest first */
    private static ?array $secrets = null;

    /** @return list<string> */
    public static function secrets(): array
    {
        if (self::$secrets !== null) {
            return self::$secrets;
        }

        $values = [];
        foreach ([$_ENV, $_SERVER, getenv() ?: []] as $source) {
            foreach ($source as $name => $value) {
                if (!is_string($name) || !is_string($value) || !preg_match(self::NAME_PATTERN, $name)) {
                    continue;
                }
                $value = trim($value);
                if (strlen($value) < self::MIN_LENGTH) {
                    continue;
                }
                $values[$value] = true;
                $values[rawurlencode($value)] = true;
                $values[urlencode($value)] = true;
            }
        }

        $values = array_map('strval', array_keys($values));
        usort($values, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return self::$secrets = $values;
    }

    /** Forget the cached secrets (tests, or after the environment changes). */
    public static function flush(): void
    {
        self::$secrets = null;
    }

    public static function redact(string $text): string
    {
        $secrets = self::secrets();

        return $secrets === [] ? $text : str_replace($secrets, self::MASK, $text);
    }

    /**
     * Strings are redacted, arrays walked, exceptions turned into the same text
     * Laravel's log formatter prints (message and trace redacted).
     */
    public static function redactValue(mixed $value, int $depth = 0): mixed
    {
        if (is_string($value)) {
            return self::redact($value);
        }
        if ($value instanceof \Throwable) {
            return self::describe($value);
        }
        if (is_array($value) && $depth < self::MAX_DEPTH) {
            foreach ($value as $key => $item) {
                $value[$key] = self::redactValue($item, $depth + 1);
            }
        }

        return $value;
    }

    public static function describe(\Throwable $e): string
    {
        $text = sprintf(
            '[object] (%s(code: %s): %s at %s:%d)',
            get_class($e),
            $e->getCode(),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ) . "\n[stacktrace]\n" . $e->getTraceAsString();

        if ($e->getPrevious() !== null) {
            $text .= "\n\n[previous exception] " . self::describe($e->getPrevious());
        }

        return self::redact($text);
    }
}

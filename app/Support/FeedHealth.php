<?php

namespace App\Support;

use App\Tools\DiscordTool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Tracks when each source of a feed last gave usable data, and posts on the
 * Discord errors channel once when it has failed for longer than its stale
 * window, and once when it recovers. Subclasses supply, through late static
 * binding, the cache `KEY` prefix, the source `LABELS`, the message
 * `PREFIX` and the stale window through
 * `staleMinutesFor($source)`.
 */
abstract class FeedHealth
{
    /** Cache key prefix, e.g. `madeira:health:`. */
    protected const KEY = '';

    /** Message prefix, e.g. `Madeira: `. */
    protected const PREFIX = '';

    /** source → label used in the messages. */
    public const LABELS = [];

    /**
     * @return string|null the Discord message posted, if any
     */
    public static function recordSuccess(string $source): ?string
    {
        Cache::forever(static::KEY.$source, Carbon::now()->getTimestamp());
        Cache::forget(static::KEY.$source.':firstFailureAt');

        if (!Cache::pull(static::KEY.$source.':announced')) {
            return null;
        }

        return static::post(static::PREFIX.static::LABELS[$source].' voltou a funcionar');
    }

    /**
     * @return string|null the Discord message posted, if any
     */
    public static function recordFailure(string $source, string $reason): ?string
    {
        // Never succeeded (fresh deploy): count from the first failure.
        if (!Cache::has(static::KEY.$source)) {
            Cache::add(static::KEY.$source.':firstFailureAt', Carbon::now()->getTimestamp());
        }

        if (!static::isStale($source) || Cache::has(static::KEY.$source.':announced')) {
            return null;
        }

        Cache::forever(static::KEY.$source.':announced', Carbon::now()->getTimestamp());

        return static::post(static::PREFIX.static::LABELS[$source].' sem dados válidos há mais de '.static::staleMinutesFor($source).' min ('.$reason.')');
    }

    public static function isStale(string $source): bool
    {
        $since = Cache::get(static::KEY.$source) ?? Cache::get(static::KEY.$source.':firstFailureAt');
        if (!$since) {
            return false;
        }

        return Carbon::createFromTimestamp($since)->diffInMinutes(Carbon::now()) > static::staleMinutesFor($source);
    }

    /** The stale window of `$source`, in minutes. */
    abstract protected static function staleMinutesFor(string $source): int;

    protected static function post(string $message): string
    {
        DiscordTool::postError($message);

        return $message;
    }
}

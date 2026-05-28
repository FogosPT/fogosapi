<?php

namespace App\Http\Concerns;

use Symfony\Component\HttpFoundation\Response;

trait CacheableResponse
{
    /**
     * Tag the response with shared (CDN) and browser cache TTLs.
     *
     * - $sMaxAge   → shared cache TTL (Cloudflare reads this)
     * - $maxAge    → browser TTL (defaults to half of $sMaxAge)
     * - $swr       → stale-while-revalidate window (defaults to $sMaxAge)
     */
    protected function cacheable(Response $response, int $sMaxAge, ?int $maxAge = null, ?int $swr = null): Response
    {
        $maxAge = $maxAge ?? (int) max(1, $sMaxAge / 2);
        $swr    = $swr    ?? $sMaxAge;

        $response->headers->set(
            'Cache-Control',
            "public, s-maxage={$sMaxAge}, max-age={$maxAge}, stale-while-revalidate={$swr}"
        );

        return $response;
    }
}

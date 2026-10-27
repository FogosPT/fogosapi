<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

/**
 * Guards write/admin endpoints with the `key` header. An unset or empty
 * API_WRITE_KEY rejects every request instead of matching a missing header.
 */
class RequireWriteKey
{
    public function handle(Request $request, \Closure $next)
    {
        $expected = (string) env('API_WRITE_KEY', '');
        $provided = (string) $request->header('key', '');

        if ($expected === '' || !hash_equals($expected, $provided)) {
            abort(401);
        }

        return $next($request);
    }
}

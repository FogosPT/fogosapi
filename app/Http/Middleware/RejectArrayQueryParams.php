<?php

namespace App\Http\Middleware;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * No endpoint takes array query parameters. Rejecting them up front keeps
 * values like `?x[$ne]=1` from reaching Mongo filters as operators and turns
 * "Array to string" / TypeError 500s into a 422.
 */
class RejectArrayQueryParams
{
    public function handle(Request $request, \Closure $next)
    {
        foreach ($request->query() as $name => $value) {
            if (is_array($value)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'invalid_parameters',
                    'message' => "Query parameter '{$name}' must be a single value.",
                ], 422);
            }
        }

        return $next($request);
    }
}

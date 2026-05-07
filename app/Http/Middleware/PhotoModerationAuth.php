<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PhotoModerationAuth
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) env('PHOTO_MODERATION_KEY', '');
        $provided = (string) $request->header('key', '');

        if ($expected === '' || !hash_equals($expected, $provided)) {
            abort(401);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class LiveActivityRateLimit
{
    public function handle(Request $request, Closure $next)
    {
        $ip     = $request->ip() ?? 'unknown';
        $fireId = (string) $request->route('id');

        $perMinKey         = "la:ip:{$ip}:min";
        $perMinLimit       = (int) env('LIVE_ACTIVITY_RATE_PER_IP_PER_MINUTE', 5);
        $perIncGlobalKey   = "la:inc:{$fireId}:hour";
        $perIncGlobalLimit = (int) env('LIVE_ACTIVITY_RATE_PER_INCIDENT_GLOBAL_PER_HOUR', 500);

        if ($retry = $this->tooMany($perMinKey, $perMinLimit)) {
            return $this->reject($retry);
        }
        if ($retry = $this->tooMany($perIncGlobalKey, $perIncGlobalLimit)) {
            return $this->reject($retry);
        }

        RateLimiter::hit($perMinKey, 60);
        RateLimiter::hit($perIncGlobalKey, 3600);

        return $next($request);
    }

    private function tooMany(string $key, int $limit): ?int
    {
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return RateLimiter::availableIn($key);
        }
        return null;
    }

    private function reject(int $retryAfter): JsonResponse
    {
        return new JsonResponse(
            ['success' => false, 'error' => 'rate_limited'],
            429,
            ['Retry-After' => (string) $retryAfter]
        );
    }
}

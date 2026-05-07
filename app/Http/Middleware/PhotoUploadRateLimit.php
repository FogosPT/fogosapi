<?php

namespace App\Http\Middleware;

use App\Models\IncidentPhoto;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class PhotoUploadRateLimit
{
    public function handle(Request $request, Closure $next)
    {
        $ip      = $request->ip() ?? 'unknown';
        $fireId  = (string) $request->route('id');

        $perMinKey         = "photo:ip:{$ip}:min";
        $perMinLimit       = (int) env('PHOTO_RATE_PER_IP_PER_MINUTE', 3);
        $perIncIpKey       = "photo:ip:{$ip}:inc:{$fireId}:hour";
        $perIncIpLimit     = (int) env('PHOTO_RATE_PER_INCIDENT_PER_IP_PER_HOUR', 8);
        $perIncGlobalKey   = "photo:inc:{$fireId}:hour";
        $perIncGlobalLimit = (int) env('PHOTO_RATE_PER_INCIDENT_GLOBAL_PER_HOUR', 80);

        if ($retry = $this->tooMany($perMinKey, $perMinLimit)) {
            return $this->reject($retry);
        }
        if ($retry = $this->tooMany($perIncIpKey, $perIncIpLimit)) {
            return $this->reject($retry);
        }
        if ($retry = $this->tooMany($perIncGlobalKey, $perIncGlobalLimit)) {
            return $this->reject($retry);
        }

        $pendingCap = (int) env('PHOTO_PENDING_PER_INCIDENT_CAP', 50);
        $pending    = IncidentPhoto::where('fire_id', $fireId)
            ->where('status', IncidentPhoto::STATUS_PENDING)
            ->count();
        if ($pending >= $pendingCap) {
            return $this->reject(3600);
        }

        RateLimiter::hit($perMinKey, 60);
        RateLimiter::hit($perIncIpKey, 3600);
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

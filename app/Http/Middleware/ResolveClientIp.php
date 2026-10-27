<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Production chain: client → Cloudflare → host nginx → docker nginx →
 * php-fpm. PHP only ever sees our own nginx as REMOTE_ADDR, and the client
 * can put anything in X-Forwarded-For, so trusting every proxy ('*') let
 * anyone choose their IP (and bypass per-IP rate limits).
 *
 * The last X-Forwarded-For entry is the address that connected to the host
 * nginx. If it is a Cloudflare edge, CF-Connecting-IP (set by Cloudflare,
 * not the client) is the client; otherwise that address is the client
 * (someone reaching the origin directly). Only applied when REMOTE_ADDR is a
 * private/loopback address, i.e. the request came through our own nginx.
 */
class ResolveClientIp
{
    // https://www.cloudflare.com/ips/ (2026-10-27). Override with CLOUDFLARE_IP_RANGES.
    private const CLOUDFLARE_RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    public function handle(Request $request, Closure $next)
    {
        $remote = (string) $request->server->get('REMOTE_ADDR', '');

        if ($remote !== '' && IpUtils::isPrivateIp($remote)) {
            $client = $this->resolve($request);
            if ($client !== null) {
                $request->server->set('REMOTE_ADDR', $client);
            }
        }

        return $next($request);
    }

    private function resolve(Request $request): ?string
    {
        $hops = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $request->server->get('HTTP_X_FORWARDED_FOR', ''))
        )));

        $peer = end($hops) ?: null;
        if ($peer === null || !$this->isIp($peer)) {
            return null;
        }

        $cfClient = trim((string) $request->server->get('HTTP_CF_CONNECTING_IP', ''));
        if ($cfClient !== '' && $this->isIp($cfClient) && IpUtils::checkIp($peer, $this->cloudflareRanges())) {
            return $cfClient;
        }

        return $peer;
    }

    private function isIp(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * @return array<int, string>
     */
    private function cloudflareRanges(): array
    {
        $configured = trim((string) env('CLOUDFLARE_IP_RANGES', ''));

        return $configured === ''
            ? self::CLOUDFLARE_RANGES
            : array_map('trim', explode(',', $configured));
    }
}

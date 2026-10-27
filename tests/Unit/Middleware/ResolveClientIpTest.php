<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ResolveClientIp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class ResolveClientIpTest extends TestCase
{
    /** @test */
    public function it_uses_cf_connecting_ip_when_the_last_hop_is_cloudflare(): void
    {
        // Client-spoofed entry, then what Cloudflare appended, then the CF edge
        // appended by the host nginx.
        $this->assertSame('9.9.9.9', $this->clientIp('172.18.0.3', '6.6.6.6, 9.9.9.9, 173.245.48.5', '9.9.9.9'));
        // Host nginx that replaces X-Forwarded-For with $remote_addr.
        $this->assertSame('9.9.9.9', $this->clientIp('172.18.0.3', '2606:4700::1', '9.9.9.9'));
    }

    /** @test */
    public function it_ignores_spoofed_headers_when_the_last_hop_is_not_cloudflare(): void
    {
        // Direct to origin: forged CF-Connecting-IP and X-Forwarded-For prefix.
        $this->assertSame('5.5.5.5', $this->clientIp('172.18.0.3', '6.6.6.6, 5.5.5.5', '6.6.6.6'));
    }

    /** @test */
    public function it_leaves_remote_addr_when_there_is_nothing_to_resolve(): void
    {
        $this->assertSame('172.18.0.3', $this->clientIp('172.18.0.3', null, null));
        $this->assertSame('172.18.0.3', $this->clientIp('172.18.0.3', 'garbage', '9.9.9.9'));
        // Public REMOTE_ADDR: not behind our nginx, headers are not trusted.
        $this->assertSame('8.8.8.8', $this->clientIp('8.8.8.8', '173.245.48.5', '9.9.9.9'));
    }

    private function clientIp(string $remote, ?string $xff, ?string $cf): string
    {
        $server = ['REMOTE_ADDR' => $remote];
        if ($xff !== null) {
            $server['HTTP_X_FORWARDED_FOR'] = $xff;
        }
        if ($cf !== null) {
            $server['HTTP_CF_CONNECTING_IP'] = $cf;
        }

        $request = Request::create('/', 'GET', [], [], [], $server);
        (new ResolveClientIp())->handle($request, fn () => new Response());

        return $request->ip();
    }
}

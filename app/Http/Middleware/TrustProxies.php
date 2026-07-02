<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class TrustProxies extends Middleware
{
    /**
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    public function handle(Request $request, Closure $next)
    {
        $request::setTrustedProxies([], $this->getTrustedHeaderNames());

        $this->setTrustedProxyIpAddresses($request);

        if ($this->shouldForceRootUrl($request)) {
            $this->applyRootUrlFromRequest($request);
        }

        return $next($request);
    }

    private function shouldForceRootUrl(Request $request): bool
    {
        if (config('app.force_root_url_from_request', false)) {
            return true;
        }

        $configured = rtrim((string) config('app.url'), '/');
        if ($configured === '') {
            return true;
        }

        $requestHost = strtolower($request->getHost());
        $configuredLower = strtolower($configured);

        $configuredPointsAtLoopback = str_contains($configuredLower, 'localhost')
            || str_contains($configuredLower, '127.0.0.1');

        $requestIsLoopback = $requestHost === '127.0.0.1'
            || $requestHost === '::1'
            || str_ends_with($requestHost, '.localhost')
            || $requestHost === 'localhost';

        // Public deployment still using a dev APP_URL → stop redirecting browsers to localhost (fixes CORS / broken API).
        if ($configuredPointsAtLoopback && ! $requestIsLoopback) {
            return true;
        }

        return false;
    }

    private function applyRootUrlFromRequest(Request $request): void
    {
        $scheme = $request->getScheme();
        $host = $request->getHost();

        if ($scheme === '' || $host === '') {
            return;
        }

        URL::forceRootUrl($scheme.'://'.$host);

        if ($scheme === 'https') {
            URL::forceScheme('https');
        }
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Fruitcake\Cors\CorsService;
use Illuminate\Http\Request;

/**
 * Answers browser CORS preflight (OPTIONS) before the rest of the global stack.
 * Avoids 405/500 from routing or later middleware when the preflight never gets proper CORS headers.
 */
class EarlyCorsMiddleware
{
    public function __construct(private CorsService $cors)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $this->cors->setOptions(config('cors', []));

        if (! $this->cors->isPreflightRequest($request)) {
            return $next($request);
        }

        if (! $this->corsPathMatches($request)) {
            return $next($request);
        }

        $response = $this->cors->handlePreflightRequest($request);

        $this->cors->varyHeader($response, 'Access-Control-Request-Method');

        return $response;
    }

    /**
     * Keep preflight handling aligned with config/cors.php paths so OPTIONS is answered
     * with 204 + headers before routing/auth, without widening CORS to unrelated routes.
     */
    private function corsPathMatches(Request $request): bool
    {
        if ($request->is('sanctum/csrf-cookie')) {
            return true;
        }

        $path = $request->decodedPath() ?? '';

        return str_starts_with($path, 'api/') || $request->is('api/*');
    }
}

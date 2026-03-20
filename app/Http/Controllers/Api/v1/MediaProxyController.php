<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

class MediaProxyController extends Controller
{
    public function __invoke(Request $request, string $path)
    {
        if ($request->getMethod() === 'OPTIONS') {
            return response('', 204, $this->corsHeaders($request));
        }

        $normalizedPath = $this->normalizePath($path);
        if ($normalizedPath === null) {
            abort(404);
        }

        $absolutePath = storage_path('app/public/' . $normalizedPath);
        if (! File::exists($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath, array_merge([
            'Content-Type' => File::mimeType($absolutePath) ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=43200',
        ], $this->corsHeaders($request)));
    }

    private function normalizePath(string $path): ?string
    {
        $cleaned = str_replace(['..', '\\'], '', $path);
        $cleaned = ltrim($cleaned, '/');
        if ($cleaned === '') {
            return null;
        }

        if (str_starts_with($cleaned, 'storage/')) {
            $cleaned = substr($cleaned, strlen('storage/'));
        }

        return $cleaned;
    }

    private function corsHeaders(Request $request): array
    {
        $configuredOrigins = config('cors.allowed_origins', ['*']);
        $origin = $request->headers->get('Origin');

        $allowOrigin = '*';
        if ($origin && (in_array('*', $configuredOrigins, true) || in_array($origin, $configuredOrigins, true))) {
            $allowOrigin = $origin;
        } elseif (! empty($configuredOrigins)) {
            $allowOrigin = $configuredOrigins[0];
        }

        $allowHeaders = $request->headers->get('Access-Control-Request-Headers', '*');

        return [
            'Access-Control-Allow-Origin' => $allowOrigin,
            'Access-Control-Allow-Methods' => 'GET,OPTIONS',
            'Access-Control-Allow-Headers' => $allowHeaders,
        ];
    }
}

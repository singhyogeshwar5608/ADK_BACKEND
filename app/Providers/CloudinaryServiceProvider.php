<?php

namespace App\Providers;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\ConfigUtils;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class CloudinaryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('cloudinary', function () {
            $url = config('services.cloudinary.url');

            // Non-empty but invalid URLs (e.g. https://..., or plain cloud name) make the SDK throw a vague error.
            if (is_string($url) && $url !== '') {
                if (ConfigUtils::isCloudinaryUrl($url)) {
                    return new Cloudinary($url);
                }

                Log::warning(
                    'CLOUDINARY_URL is set but is not a valid cloudinary:// URL; using CLOUDINARY_CLOUD_NAME / API keys instead.',
                );
            }

            $cloudName = (string) config('services.cloudinary.cloud_name', '');
            $apiKey = (string) config('services.cloudinary.api_key', '');
            $apiSecret = (string) config('services.cloudinary.api_secret', '');

            if ($cloudName === '') {
                throw new \RuntimeException(
                    'Cloudinary is not configured: cloud name is missing. In backend/.env set either ' .
                    'CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME (from Cloudinary Dashboard → API Keys), ' .
                    'or set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET. Then run: php artisan config:clear'
                );
            }

            return new Cloudinary([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ],
            ]);
        });
    }
}

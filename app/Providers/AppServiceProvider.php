<?php

namespace App\Providers;

use App\Http\Middleware\Authenticate as AppAuthenticate;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Authenticate::class, AppAuthenticate::class);

        // Dev-only packages: excluded from package:discovery so production (--no-dev) never loads them from cache.
        if (class_exists(\Laravel\Pail\PailServiceProvider::class)) {
            $this->app->register(\Laravel\Pail\PailServiceProvider::class);
        }
        if (class_exists(\Laravel\Sail\SailServiceProvider::class)) {
            $this->app->register(\Laravel\Sail\SailServiceProvider::class);
        }
        if (class_exists(\NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider::class)) {
            $this->app->register(\NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

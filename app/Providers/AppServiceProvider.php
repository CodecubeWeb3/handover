<?php

namespace App\Providers;

use App\Support\FeatureFlags;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureFlags::class, function ($app) {
            return new FeatureFlags(
                $app->make(ConfigRepository::class),
                $app->make(CacheFactory::class)
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
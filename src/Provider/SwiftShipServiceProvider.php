<?php

namespace zfhassaan\swiftship\Provider;

use Illuminate\Support\ServiceProvider;
use zfhassaan\swiftship\SwiftShip;

class SwiftShipServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application Services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/SwiftShipConfig.php'  => config_path('swiftship.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__ . '/../../config/SwiftShipConfig.php', 'swiftship');

        // Register the main class to use with the facade
        $this->app->singleton('payfast', function () {
            return new SwiftShip();
        });
    }
}

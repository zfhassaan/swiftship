<?php

namespace zfhassaan\swiftship\Provider;

use Illuminate\Support\ServiceProvider;
use zfhassaan\swiftship\SwiftShip;

class SwiftShipServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/SwiftShipConfig.php'  => config_path('swiftship.php'),
            ], 'swift-ship-config');

            $this->publishes([
                __DIR__.'/../../tests/Couriers/TCSTest.php'  => base_path('tests/Unit/SwiftTCSTest.php'),
                __DIR__.'/../../tests/SwiftShipTest.php'  => base_path('tests/Unit/SwiftShipTest.php'),
            ], 'tests');
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
        $this->app->singleton('swiftship', function () {
            return new SwiftShip();
        });
    }
}

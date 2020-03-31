<?php

namespace Igaster\LaravelMetrics;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

// Service Provider is auto-discovered by Laravel (through composer.json configuration)

class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        /*--------------------------------------------------------------------------
        | Bind in IOC
        |--------------------------------------------------------------------------*/

        $this->app->singleton('igaster.xxx', function () {
            return null; // eg new myService();
        });

    }

    public function boot()
    {
        $this->handleConfigs();
        $this->handleMigrations();
        $this->handleViews();
        $this->handleRoutes();
        $this->handleTranslations();
        $this->handleConsoleCommands();
        $this->handleMiddleware();
    }

    /*--------------------------------------------------------------------------
    | Configuration files
    |--------------------------------------------------------------------------*/

    private function handleConfigs()
    {
        // Load default configuration (if not published/overridden)
        $this->mergeConfigFrom(__DIR__ . '/Config/config.php', 'package-config');

        // Publish configuration file
        $this->publishes([
            __DIR__ . '/Config/config.php' => config_path('package-config.php'),
        ]);
    }

    /*--------------------------------------------------------------------------
    | Database Migrations
    |--------------------------------------------------------------------------*/

    private function handleMigrations()
    {

        $this->loadMigrationsFrom(__DIR__ . '/Migrations');

        // Optional: Publish the migrations:
        $this->publishes([
            __DIR__ . '/Migrations' => base_path('database/migrations'),
        ]);
    }

    /*--------------------------------------------------------------------------
    | Load Translations
    |--------------------------------------------------------------------------*/

    private function handleTranslations()
    {
        $this->loadTranslationsFrom(__DIR__ . '/Translations', 'LaravelMetrics');
    }

    /*--------------------------------------------------------------------------
    | Load/Publish Views
    |--------------------------------------------------------------------------*/

    private function handleViews()
    {
        $this->loadViewsFrom(__DIR__ . '/Views', 'LaravelMetrics');

        $this->publishes([
            __DIR__ . '/views' => base_path('resources/views/vendor/LaravelMetrics'),
        ]);
    }

    /*--------------------------------------------------------------------------
    | Register Console Commands
    |--------------------------------------------------------------------------*/

    private function handleConsoleCommands()
    {
        // Register Console Commands
        if ($this->app->runningInConsole()) {

            $this->commands([
                \Igaster\LaravelMetrics\Commands\ExampleCommand::class,
            ]);

        }
    }

    /*--------------------------------------------------------------------------
    | Register Routes
    |--------------------------------------------------------------------------*/

    private function handleRoutes()
    {
        include __DIR__ . '/routes.php';
    }

    /*--------------------------------------------------------------------------
    | Register Middleware
    |--------------------------------------------------------------------------*/

    private function handleMiddleware()
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $router->pushMiddlewareToGroup('web', \Igaster\LaravelMetrics\Middleware\ExampleMiddleware::class);
    }
}


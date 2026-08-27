<?php

declare(strict_types=1);

namespace LaraDb;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LaraDb\Drivers\DriverInterface;
use LaraDb\Exceptions\UnsupportedDriverException;

final class LaraDbServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laradb.php', 'laradb');

        // Resolved lazily: an application that never opens the viewer never
        // touches the database because of us.
        $this->app->bind(DriverInterface::class, function (): DriverInterface {
            $config = $this->app->make('config');

            /** @var string|null $name */
            $name = $config->get('laradb.connection');
            $name = $name === null || $name === '' ? (string) $config->get('database.default') : $name;

            // Check the engine against the config before touching the
            // database: Laravel's own factory throws something far less
            // helpful when it meets a driver it cannot build.
            $driver = $config->get('database.connections.'.$name.'.driver');

            if (is_string($driver) && ! DriverFactory::supports($driver)) {
                throw UnsupportedDriverException::forDriver($driver, DriverFactory::supported());
            }

            /** @var DatabaseManager $manager */
            $manager = $this->app->make('db');

            /** @var Connection $connection */
            $connection = $manager->connection($name);

            // getReadPdo() points at the read replica when one is configured,
            // which is exactly where a read-only viewer belongs.
            return DriverFactory::make($connection->getReadPdo(), $connection->getDriverName());
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laradb');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laradb.php' => $this->app->configPath('laradb.php'),
            ], 'laradb-config');

            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/laradb'),
            ], 'laradb-views');
        }

        if ($this->routesAreEnabled()) {
            $this->registerRoutes();
        }
    }

    /**
     * The viewer is off outside of `local` unless it is explicitly turned on.
     * Exposing the contents of a database is not something that should ever
     * happen because someone forgot to set a variable.
     */
    private function routesAreEnabled(): bool
    {
        $enabled = $this->app->make('config')->get('laradb.enabled');

        if ($enabled === null) {
            return $this->app->environment('local');
        }

        return filter_var($enabled, FILTER_VALIDATE_BOOL);
    }

    private function registerRoutes(): void
    {
        $config = $this->app->make('config');

        /** @var array<int, string> $middleware */
        $middleware = $config->get('laradb.middleware', ['web', 'auth']);

        Route::group([
            'prefix' => (string) $config->get('laradb.route_prefix', 'db'),
            'middleware' => $middleware,
            'as' => 'laradb.',
            'namespace' => null,
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }
}

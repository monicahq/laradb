<?php

namespace LaraDb;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use LaraDb\View\Components\DBLayout;

class LaraDbServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/laradb.php' => config_path('laradb.php'),
        ], 'config');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laradb');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/courier'),
            ]);
        }

        Blade::component('db-layout', DBLayout::class);
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laradb.php', 'laradb');
    }
}

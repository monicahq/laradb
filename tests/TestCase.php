<?php

declare(strict_types=1);

namespace LaraDb\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LaraDb\LaraDbServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Config applied on top of the defaults, so a test can rebuild the
     * application with a different setup. Routes are registered while the
     * provider boots, so changing them means recreating the application.
     *
     * @var array<string, mixed>
     */
    protected array $configOverrides = [];

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LaraDbServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app->make('config');

        // The `web` middleware group encrypts cookies, so the rebuilt
        // applications need a key of their own.
        $config->set('app.key', 'base64:uA/XEme0vpegJz/rKSk3ys2uzEfXZA0Ca2P0e1M8vRU=');

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // The viewer is off outside `local` by default, so the test suite has
        // to opt in explicitly — which is exactly the behaviour we want.
        $config->set('laradb.enabled', true);
        $config->set('laradb.middleware', ['web']);
        $config->set('laradb.per_page', 3);

        foreach ($this->configOverrides as $key => $value) {
            $config->set($key, $value);
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->createTestSchema();
    }

    /**
     * Rebuild the application with extra configuration, then put the test
     * schema back (the in-memory database goes away with the old app).
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function reloadApplicationWith(array $overrides): void
    {
        $this->configOverrides = array_merge($this->configOverrides, $overrides);

        $this->refreshApplication();

        $this->createTestSchema();
    }

    protected function createTestSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('empty_table', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('label')->nullable();
        });

        DB::table('users')->insert(
            array_map(static fn (int $i): array => [
                'name' => 'User '.$i,
                'email' => $i % 3 === 0 ? null : 'user'.$i.'@example.test',
                'created_at' => '2026-01-01 10:00:00',
                'updated_at' => '2026-01-01 10:00:00',
            ], range(1, 10)),
        );
    }
}

<?php

declare(strict_types=1);

namespace LaraDb\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaraDb\Tests\Fixtures\DenyMiddleware;
use LaraDb\Tests\TestCase;

final class RouteRegistrationTest extends TestCase
{
    public function test_the_viewer_is_reachable_when_enabled(): void
    {
        $this->get('/db')->assertOk();
    }

    public function test_it_registers_no_route_when_disabled(): void
    {
        $this->reloadApplicationWith(['laradb.enabled' => false]);

        $this->get('/db')->assertNotFound();
        $this->get('/db/tables/users')->assertNotFound();
    }

    public function test_it_stays_off_outside_local_when_left_unconfigured(): void
    {
        // Null means "local only", and the test environment is not local.
        $this->reloadApplicationWith(['laradb.enabled' => null]);

        $this->get('/db')->assertNotFound();
    }

    public function test_the_route_prefix_is_configurable(): void
    {
        $this->reloadApplicationWith(['laradb.route_prefix' => 'admin/database']);

        $this->get('/admin/database')->assertOk();
        $this->get('/admin/database/tables/users')->assertOk();
        $this->get('/db')->assertNotFound();
    }

    public function test_the_middleware_is_applied(): void
    {
        $this->reloadApplicationWith(['laradb.middleware' => ['web', DenyMiddleware::class]]);

        $this->get('/db')->assertForbidden();
        $this->get('/db/tables/users')->assertForbidden();
    }

    /**
     * A misconfigured security control must not become an absent one. Laravel
     * drops a group's `middleware` key when it is not set, so a null or empty
     * config would otherwise publish the whole database unauthenticated.
     */
    public function test_an_empty_middleware_config_falls_back_to_the_safe_default(): void
    {
        foreach ([null, []] as $configured) {
            $this->reloadApplicationWith(['laradb.middleware' => $configured]);

            foreach ($this->laradbRoutes() as $uri => $middleware) {
                $this->assertContains('auth', $middleware, $uri.' was published without authentication.');
                $this->assertContains('web', $middleware, $uri.' was published without the web group.');
            }
        }
    }

    public function test_a_middleware_config_given_as_a_string_still_applies(): void
    {
        $this->reloadApplicationWith(['laradb.middleware' => DenyMiddleware::class]);

        $this->get('/db')->assertForbidden();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function laradbRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'db')) {
                $routes[$route->uri()] = $route->gatherMiddleware();
            }
        }

        $this->assertNotSame([], $routes, 'No LaraDb routes were registered at all.');

        return $routes;
    }

    public function test_the_routes_are_named(): void
    {
        $this->assertSame(url('/db'), route('laradb.index'));
        $this->assertSame(url('/db/tables/users'), route('laradb.table', ['table' => 'users']));
    }

    public function test_the_viewer_refuses_anything_but_reading(): void
    {
        $this->post('/db')->assertMethodNotAllowed();
        $this->put('/db/tables/users')->assertMethodNotAllowed();
        $this->delete('/db/tables/users')->assertMethodNotAllowed();
    }
}

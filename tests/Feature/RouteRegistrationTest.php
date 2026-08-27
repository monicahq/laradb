<?php

declare(strict_types=1);

namespace LaraDb\Tests\Feature;

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

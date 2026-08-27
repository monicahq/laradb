<?php

declare(strict_types=1);

namespace LaraDb\Tests\Feature;

use LaraDb\Tests\TestCase;

final class ViewerTest extends TestCase
{
    public function test_the_index_lists_the_tables_and_shows_the_first_one(): void
    {
        $response = $this->get('/db');

        $response->assertOk()
            ->assertSee('empty_table')
            ->assertSee('users')
            // Tables are alphabetical, so `empty_table` is the one selected.
            ->assertSee('This table is empty');
    }

    public function test_it_shows_the_requested_table(): void
    {
        $response = $this->get('/db?table=users');

        $response->assertOk()
            ->assertSee('User 1')
            ->assertSee('User 3')
            // per_page is 3 in the test environment.
            ->assertDontSee('User 4');
    }

    public function test_it_renders_null_values_distinctly(): void
    {
        $this->get('/db?table=users')->assertOk()->assertSee('NULL');
    }

    public function test_it_shows_the_column_metadata(): void
    {
        $this->get('/db?table=users')
            ->assertOk()
            ->assertSee('created_at')
            ->assertSee('pk');
    }

    public function test_it_paginates(): void
    {
        $this->get('/db?table=users&page=2')
            ->assertOk()
            ->assertSee('User 4')
            ->assertSee('User 6')
            ->assertDontSee('User 7');

        $this->get('/db?table=users&page=4')
            ->assertOk()
            ->assertSee('User 10');
    }

    public function test_it_clamps_a_page_beyond_the_last_one(): void
    {
        $this->get('/db?table=users&page=9999')
            ->assertOk()
            ->assertSee('User 10')
            ->assertDontSee('User 1<');
    }

    public function test_it_404s_on_a_table_outside_the_schema(): void
    {
        $this->get('/db?table=does_not_exist')->assertNotFound();
        $this->get('/db/tables/does_not_exist')->assertNotFound();
    }

    public function test_the_fragment_endpoint_returns_html_without_the_layout(): void
    {
        $response = $this->get('/db/tables/users');

        $response->assertOk()
            ->assertSee('User 1')
            ->assertDontSee('<!DOCTYPE html>', false)
            ->assertDontSee('cdn.tailwindcss.com');
    }

    public function test_the_fragment_endpoint_returns_json_on_demand(): void
    {
        $response = $this->get('/db/tables/users?format=json&page=2');

        $response->assertOk()
            ->assertJsonPath('table', 'users')
            ->assertJsonPath('page', 2)
            ->assertJsonPath('per_page', 3)
            ->assertJsonPath('total', 10)
            ->assertJsonPath('last_page', 4)
            ->assertJsonPath('rows.0.name', 'User 4')
            ->assertJsonPath('columns.0.name', 'id')
            ->assertJsonPath('columns.0.primary_key', true)
            ->assertJsonCount(3, 'rows');
    }

    public function test_the_fragment_endpoint_honours_the_accept_header(): void
    {
        $this->getJson('/db/tables/users')
            ->assertOk()
            ->assertJsonPath('table', 'users');
    }

    public function test_it_returns_a_json_404_for_an_unknown_table(): void
    {
        $this->getJson('/db/tables/nope')
            ->assertNotFound()
            ->assertJsonPath('message', 'Unknown table.');
    }

    public function test_an_empty_table_renders_an_empty_state(): void
    {
        $this->get('/db/tables/empty_table')
            ->assertOk()
            ->assertSee('This table is empty');
    }

    public function test_it_never_prints_the_connection_credentials(): void
    {
        $this->reloadApplicationWith([
            'database.connections.testing.username' => 'db_user',
            'database.connections.testing.password' => 'sup3r-s3cret',
        ]);

        $this->get('/db?table=users')
            ->assertOk()
            ->assertDontSee('sup3r-s3cret')
            ->assertDontSee('db_user');
    }

    public function test_a_broken_connection_renders_an_error_instead_of_a_blank_page(): void
    {
        $this->reloadApplicationWith([
            'laradb.connection' => 'broken',
            'database.connections.broken' => [
                'driver' => 'sqlite',
                'database' => '/nonexistent-directory/does-not-exist.sqlite',
                'prefix' => '',
            ],
        ]);

        $response = $this->get('/db');

        $response->assertStatus(500)
            ->assertSee('The database could not be read')
            ->assertSee('Could not open the [broken] database connection.')
            // The underlying PDO message quotes the path; it stays in the log.
            ->assertDontSee('nonexistent-directory');
    }

    public function test_an_unsupported_engine_is_reported_clearly(): void
    {
        $this->reloadApplicationWith([
            'laradb.connection' => 'mongo',
            'database.connections.mongo' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        // Swap the driver name only once the connection is resolvable, so the
        // failure comes from our factory and not from Laravel's.
        config(['database.connections.mongo.driver' => 'sqlsrv']);

        $this->get('/db')->assertStatus(500)->assertSee('Unsupported database driver');
    }
}

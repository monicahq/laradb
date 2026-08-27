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
            ->assertSee('accounts')
            ->assertSee('empty_table')
            ->assertSee('users')
            // Tables are alphabetical, so `accounts` is the one selected.
            ->assertSee('Acme')
            ->assertSee('Globex');
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
            ->assertSee('PK')
            ->assertSee('FK');
    }

    public function test_it_names_the_table_a_foreign_key_points_at(): void
    {
        $this->get('/db?table=users')
            ->assertOk()
            ->assertSee('References accounts.id');
    }

    public function test_it_shows_what_it_knows_about_the_database(): void
    {
        $response = $this->get('/db?table=users');

        $response->assertOk()
            // Engine, version and the size of the schema being browsed.
            ->assertSee('sqlite')
            ->assertSee((string) \SQLite3::version()['versionString'])
            ->assertSee('3 tables')
            // SQLite reports its own settings in the header strip.
            ->assertSee('journal')
            ->assertSee('page');
    }

    public function test_it_shows_the_statement_it_ran_and_how_long_it_took(): void
    {
        $this->get('/db?table=users')
            ->assertOk()
            ->assertSee('SELECT * FROM &quot;users&quot; LIMIT 3 OFFSET 0', false)
            ->assertSee(' ms');
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
            ->assertDontSee('<!DOCTYPE html>', false);
    }

    public function test_the_page_pulls_nothing_but_a_font_from_the_network(): void
    {
        $response = $this->get('/db');

        $response->assertOk()
            ->assertDontSee('cdn.tailwindcss.com')
            ->assertDontSee('cdn.jsdelivr.net')
            ->assertDontSee('unpkg.com');
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
            ->assertJsonPath('columns.1.foreign_key', 'accounts.id')
            ->assertJsonPath('sql', 'SELECT * FROM "users" LIMIT 3 OFFSET 3')
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

    public function test_a_foreign_key_cell_links_to_the_row_it_points_at(): void
    {
        $this->get('/db?table=users')
            ->assertOk()
            // The value itself is the link, and it carries where it came from.
            ->assertSee('table=accounts&column=id&value=2')
            ->assertSee('from=users.account_id')
            ->assertSee('Go to accounts.id = 2');
    }

    public function test_following_a_foreign_key_shows_the_one_row(): void
    {
        $response = $this->get('/db?table=accounts&column=id&value=2&from=users.account_id');

        $response->assertOk()
            ->assertSee('Globex')
            ->assertDontSee('Acme')
            // The chip says what is being shown and where it came from.
            ->assertSee('users.account_id')
            ->assertSee('id = 2')
            ->assertSee('1 row');
    }

    public function test_following_a_foreign_key_lands_on_a_single_row(): void
    {
        // A foreign key has to reference a unique column, so the far end of
        // one is always exactly one row — never a page of them. per_page is 3
        // here and there is still no pager.
        $this->get('/db?table=accounts&column=id&value=1')
            ->assertOk()
            ->assertSee('1 row')
            ->assertDontSee('← prev');
    }

    public function test_it_refuses_to_filter_on_a_column_no_foreign_key_targets(): void
    {
        // `email` is a real column. Nothing references it, so it is not a
        // door the URL gets to open.
        $this->get('/db?table=users&column=email&value=user1@example.test')->assertNotFound();
        $this->get('/db/tables/users?column=email&value=user1@example.test')->assertNotFound();
    }

    public function test_a_hostile_filter_value_is_answered_with_no_rows(): void
    {
        $this->get("/db?table=accounts&column=id&value=' OR 1=1 --")
            ->assertOk()
            ->assertSee('No row matches')
            ->assertDontSee('Globex');
    }

    public function test_an_absurdly_long_filter_value_is_refused(): void
    {
        $this->get('/db?table=accounts&column=id&value='.str_repeat('x', 256))->assertNotFound();
    }

    public function test_an_origin_that_is_not_a_real_foreign_key_is_dropped(): void
    {
        // The filter still applies; only the "came from" label is discarded,
        // because nothing in the schema backs it up.
        $this->get('/db?table=accounts&column=id&value=2&from=users.name')
            ->assertOk()
            ->assertSee('Globex')
            ->assertSee('id = 2')
            ->assertDontSee('users.name');
    }

    public function test_the_json_endpoint_reports_the_filter(): void
    {
        $this->getJson('/db/tables/accounts?column=id&value=2')
            ->assertOk()
            ->assertJsonPath('filter.column', 'id')
            ->assertJsonPath('filter.value', '2')
            ->assertJsonPath('total', 1);

        $this->getJson('/db/tables/accounts')
            ->assertOk()
            ->assertJsonPath('filter', null);
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

    public function test_it_never_prints_the_path_of_a_database_outside_the_project(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'laradb').'.sqlite';
        touch($file);

        try {
            $this->reloadApplicationWith([
                'laradb.connection' => 'elsewhere',
                'database.connections.elsewhere' => [
                    'driver' => 'sqlite',
                    'database' => $file,
                    'prefix' => '',
                ],
            ]);

            // The header names the database, but a server path is not
            // something a web page gets to say out loud.
            $this->get('/db')
                ->assertOk()
                ->assertSee(basename($file))
                ->assertDontSee(dirname($file));
        } finally {
            @unlink($file);
        }
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

<?php

declare(strict_types=1);

namespace LaraDb\Tests\Unit;

use LaraDb\Drivers\SqliteDriver;
use LaraDb\Exceptions\UnknownTableException;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * The reading core is framework-agnostic, so it is tested against a bare PDO
 * handle: no Laravel involved anywhere in this file.
 */
final class SqliteDriverTest extends TestCase
{
    private PDO $pdo;

    private SqliteDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL, body TEXT)');
        $this->pdo->exec('CREATE TABLE tags (id INTEGER PRIMARY KEY, label TEXT)');

        for ($i = 1; $i <= 7; $i++) {
            $this->pdo->exec("INSERT INTO posts (title, body) VALUES ('Post {$i}', 'Body {$i}')");
        }

        $this->driver = new SqliteDriver($this->pdo);
    }

    public function test_it_reports_its_engine_name(): void
    {
        $this->assertSame('sqlite', $this->driver->name());
    }

    public function test_it_lists_tables_alphabetically_with_their_row_count(): void
    {
        $tables = $this->driver->listTables();

        $this->assertCount(2, $tables);
        $this->assertSame('posts', $tables[0]->name);
        $this->assertSame('tags', $tables[1]->name);
        $this->assertSame(7, $tables[0]->approximateRowCount);
        $this->assertSame(0, $tables[1]->approximateRowCount);
    }

    public function test_it_hides_sqlite_internal_tables(): void
    {
        $this->pdo->exec('CREATE TABLE seq (id INTEGER PRIMARY KEY AUTOINCREMENT)');

        $names = array_map(static fn ($table): string => $table->name, (new SqliteDriver($this->pdo))->listTables());

        $this->assertContains('seq', $names);
        $this->assertNotContains('sqlite_sequence', $names);
    }

    public function test_it_reads_columns_with_their_metadata(): void
    {
        $columns = $this->driver->getColumns('posts');

        $this->assertSame(['id', 'title', 'body'], array_map(static fn ($c): string => $c->name, $columns));
        $this->assertTrue($columns[0]->primaryKey);
        $this->assertFalse($columns[1]->primaryKey);
        $this->assertFalse($columns[1]->nullable);
        $this->assertTrue($columns[2]->nullable);
        $this->assertSame('TEXT', $columns[1]->type);
    }

    public function test_it_counts_rows_exactly(): void
    {
        $this->assertSame(7, $this->driver->getRowCount('posts'));
        $this->assertSame(0, $this->driver->getRowCount('tags'));
    }

    public function test_it_paginates_rows(): void
    {
        $page = $this->driver->getRows('posts', 2, 3);

        $this->assertSame('posts', $page->table);
        $this->assertSame(2, $page->page);
        $this->assertSame(3, $page->perPage);
        $this->assertSame(7, $page->total);
        $this->assertSame(3, $page->lastPage());
        $this->assertCount(3, $page->rows);
        $this->assertSame('Post 4', $page->rows[0]['title']);
        $this->assertSame(4, $page->from());
        $this->assertSame(6, $page->to());
    }

    public function test_it_returns_a_partial_last_page(): void
    {
        $page = $this->driver->getRows('posts', 3, 3);

        $this->assertCount(1, $page->rows);
        $this->assertSame('Post 7', $page->rows[0]['title']);
        $this->assertFalse($page->hasNextPage());
        $this->assertTrue($page->hasPreviousPage());
    }

    public function test_it_clamps_an_out_of_range_page_instead_of_failing(): void
    {
        $this->assertSame(3, $this->driver->getRows('posts', 900, 3)->page);
        $this->assertSame(1, $this->driver->getRows('posts', -5, 3)->page);
    }

    public function test_it_clamps_the_page_size(): void
    {
        $this->assertSame(1, $this->driver->getRows('posts', 1, 0)->perPage);
        $this->assertSame(SqliteDriver::MAX_PER_PAGE, $this->driver->getRows('posts', 1, 999999)->perPage);
    }

    public function test_an_empty_table_yields_an_empty_page_and_not_an_error(): void
    {
        $page = $this->driver->getRows('tags', 1, 25);

        $this->assertTrue($page->isEmpty());
        $this->assertSame(0, $page->total);
        $this->assertSame(1, $page->lastPage());
        $this->assertNull($page->from());
        $this->assertNull($page->to());
        $this->assertCount(2, $page->columns);
    }

    public function test_it_refuses_a_table_that_is_not_in_the_schema(): void
    {
        $this->expectException(UnknownTableException::class);

        $this->driver->getRows('posts; DROP TABLE posts', 1, 10);
    }

    public function test_it_refuses_an_unknown_table_for_every_read_method(): void
    {
        foreach (['getColumns', 'getRowCount'] as $method) {
            try {
                $this->driver->{$method}('does_not_exist');
                $this->fail($method.'() accepted an unknown table.');
            } catch (UnknownTableException $e) {
                $this->assertStringContainsString('does_not_exist', $e->getMessage());
            }
        }
    }

    public function test_it_survives_a_table_name_containing_a_quote(): void
    {
        $this->pdo->exec('CREATE TABLE "we""ird" (id INTEGER PRIMARY KEY, value TEXT)');
        $this->pdo->exec('INSERT INTO "we""ird" (value) VALUES (\'ok\')');

        $driver = new SqliteDriver($this->pdo);

        $this->assertSame('"we""ird"', $driver->quoteIdentifier('we"ird'));
        $this->assertSame(1, $driver->getRowCount('we"ird'));
        $this->assertSame('ok', $driver->getRows('we"ird', 1, 10)->rows[0]['value']);
    }

    public function test_it_replaces_binary_values_with_a_readable_marker(): void
    {
        $this->pdo->exec('CREATE TABLE files (id INTEGER PRIMARY KEY, data BLOB)');
        $this->pdo->exec("INSERT INTO files (data) VALUES (X'FFFEFDFC')");

        $rows = (new SqliteDriver($this->pdo))->getRows('files', 1, 10)->rows;

        $this->assertSame('[binary, 4 B]', $rows[0]['data']);
    }

    public function test_it_keeps_null_values_as_null(): void
    {
        $this->pdo->exec('INSERT INTO tags (label) VALUES (NULL)');

        $rows = (new SqliteDriver($this->pdo))->getRows('tags', 1, 10)->rows;

        $this->assertNull($rows[0]['label']);
    }
}

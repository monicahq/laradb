<?php

declare(strict_types=1);

namespace LaraDb\Tests\Unit;

use LaraDb\Drivers\SqliteDriver;
use LaraDb\DTO\RowFilter;
use LaraDb\Exceptions\UnknownColumnException;
use LaraDb\Exceptions\UnknownTableException;
use PDO;
use PHPUnit\Framework\TestCase;
use SQLite3;

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
        $this->pdo->exec('CREATE TABLE tags (id INTEGER PRIMARY KEY, label TEXT)');
        $this->pdo->exec(
            'CREATE TABLE posts (
                id INTEGER PRIMARY KEY,
                tag_id INTEGER REFERENCES tags (id),
                title TEXT NOT NULL,
                body TEXT
            )'
        );
        $this->pdo->exec('CREATE INDEX posts_title_index ON posts (title)');

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

        $this->assertSame(['id', 'tag_id', 'title', 'body'], array_map(static fn ($c): string => $c->name, $columns));
        $this->assertTrue($columns[0]->primaryKey);
        $this->assertFalse($columns[2]->primaryKey);
        $this->assertFalse($columns[2]->nullable);
        $this->assertTrue($columns[3]->nullable);
        $this->assertSame('TEXT', $columns[2]->type);
    }

    public function test_it_points_a_column_at_the_one_it_references(): void
    {
        $columns = $this->driver->getColumns('posts');

        $this->assertNull($columns[0]->foreignKey);
        $this->assertSame('tags.id', $columns[1]->foreignKey);
        $this->assertSame(['tag_id' => 'tags.id'], $this->driver->getForeignKeys('posts'));
        $this->assertSame([], $this->driver->getForeignKeys('tags'));
    }

    public function test_it_lists_the_columns_a_foreign_key_points_at(): void
    {
        // posts.tag_id references tags.id, so tags.id — and nothing else — is
        // a legal thing to follow a foreign key to.
        $this->assertSame(['tags' => ['id']], $this->driver->foreignKeyTargets());
    }

    public function test_it_filters_to_the_row_a_foreign_key_points_at(): void
    {
        $this->pdo->exec("INSERT INTO tags (id, label) VALUES (7, 'ops'), (8, 'infra')");

        $page = (new SqliteDriver($this->pdo))->getRows('tags', 1, 25, new RowFilter('id', '7'));

        $this->assertSame(1, $page->total);
        $this->assertCount(1, $page->rows);
        $this->assertSame('ops', $page->rows[0]['label']);
        $this->assertNotNull($page->filter);
        $this->assertSame('id', $page->filter->column);
        $this->assertSame('7', $page->filter->value);
    }

    public function test_the_filtered_statement_binds_its_value(): void
    {
        $page = $this->driver->getRows('tags', 1, 25, new RowFilter('id', '4242'));

        $this->assertSame('SELECT * FROM "tags" WHERE "id" = :value LIMIT 25 OFFSET 0', $page->sql);
        $this->assertStringNotContainsString('4242', $page->sql);
    }

    public function test_a_filtered_count_only_counts_what_matches(): void
    {
        $this->pdo->exec("INSERT INTO tags (id, label) VALUES (7, 'ops'), (8, 'infra')");
        $driver = new SqliteDriver($this->pdo);

        $this->assertSame(2, $driver->getRowCount('tags'));
        $this->assertSame(1, $driver->getRowCount('tags', new RowFilter('id', '7')));
    }

    public function test_it_refuses_to_filter_on_a_column_nothing_references(): void
    {
        // `title` is a perfectly real column of `posts`. It is not a foreign
        // key target, so it is not something a URL may filter on.
        $this->expectException(UnknownColumnException::class);

        $this->driver->getRows('posts', 1, 25, new RowFilter('title', 'Post 1'));
    }

    public function test_a_hostile_filter_value_finds_nothing_and_breaks_nothing(): void
    {
        $this->pdo->exec("INSERT INTO tags (id, label) VALUES (7, 'ops')");

        $page = (new SqliteDriver($this->pdo))->getRows('tags', 1, 25, new RowFilter('id', "' OR 1=1 --"));

        $this->assertTrue($page->isEmpty());
        $this->assertSame(0, $page->total);
    }

    public function test_it_describes_the_database(): void
    {
        $info = $this->driver->describe();

        $this->assertSame('sqlite', $info->engine);
        $this->assertSame(SQLite3::version()['versionString'], $info->version);
        $this->assertSame(':memory:', $info->name);
        $this->assertSame(2, $info->tableCount);
        // The one we declared; SQLite's own autoindexes are not ours to count.
        $this->assertSame(1, $info->indexCount);
        $this->assertNotNull($info->sizeInBytes);
        $this->assertGreaterThan(0, $info->sizeInBytes);
        $this->assertNotNull($info->formattedSize());
    }

    public function test_it_reports_the_engine_settings(): void
    {
        $metadata = $this->driver->metadata();

        $this->assertArrayHasKey('page', $metadata);
        $this->assertArrayHasKey('journal', $metadata);
        $this->assertArrayHasKey('enc', $metadata);
        $this->assertSame('UTF-8', $metadata['enc']);
        $this->assertContains($metadata['fk'], ['on', 'off']);
    }

    public function test_it_describes_the_database_only_once(): void
    {
        $this->driver->describe();
        $afterFirst = $this->driver->queryCount();

        $this->driver->describe();

        $this->assertSame($afterFirst, $this->driver->queryCount());
    }

    public function test_it_counts_the_statements_it_runs(): void
    {
        $before = $this->driver->queryCount();

        $this->driver->getRowCount('posts');

        $this->assertGreaterThan($before, $this->driver->queryCount());
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
        $this->assertSame('SELECT * FROM "posts" LIMIT 3 OFFSET 3', $page->sql);
        $this->assertGreaterThan(0.0, $page->durationMs);
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
        foreach (['getColumns', 'getRowCount', 'getForeignKeys'] as $method) {
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

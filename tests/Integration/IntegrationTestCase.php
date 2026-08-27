<?php

declare(strict_types=1);

namespace LaraDb\Tests\Integration;

use LaraDb\Drivers\AbstractDriver;
use LaraDb\DTO\RowFilter;
use LaraDb\Exceptions\UnknownColumnException;
use LaraDb\Exceptions\UnknownTableException;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The shared contract every server-backed driver must satisfy.
 *
 * These are skipped unless a server is reachable, so `composer test` stays
 * offline-friendly; CI provides the services and runs them for real.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected PDO $pdo;

    protected AbstractDriver $driver;

    abstract protected function connect(): PDO;

    abstract protected function makeDriver(PDO $pdo): AbstractDriver;

    /**
     * @return list<string>
     */
    abstract protected function schemaStatements(): array;

    abstract protected function expectedEngineName(): string;

    abstract protected function expectedQuotedIdentifier(): string;

    /**
     * The engine settings this driver is expected to report, in order.
     *
     * @return list<string>
     */
    abstract protected function expectedMetadataKeys(): array;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->pdo = $this->connect();
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Throwable $e) {
            $this->markTestSkipped(static::class.': no server reachable ('.$e->getMessage().').');
        }

        $this->dropSchema();

        foreach ($this->schemaStatements() as $statement) {
            $this->pdo->exec($statement);
        }

        for ($i = 1; $i <= 7; $i++) {
            $this->pdo->exec("INSERT INTO laradb_posts (title, body) VALUES ('Post {$i}', 'Body {$i}')");
        }

        $this->driver = $this->makeDriver($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->dropSchema();
        }

        parent::tearDown();
    }

    private function dropSchema(): void
    {
        foreach (['laradb_posts', 'laradb_tags'] as $table) {
            $this->pdo->exec('DROP TABLE IF EXISTS '.$table);
        }
    }

    public function test_it_reports_its_engine_name(): void
    {
        $this->assertSame($this->expectedEngineName(), $this->driver->name());
    }

    public function test_it_quotes_identifiers_the_way_the_engine_expects(): void
    {
        $this->assertSame($this->expectedQuotedIdentifier(), $this->driver->quoteIdentifier('laradb_posts'));
    }

    public function test_it_lists_the_tables_of_the_current_schema(): void
    {
        $names = array_map(static fn ($table): string => $table->name, $this->driver->listTables());

        $this->assertContains('laradb_posts', $names);
        $this->assertContains('laradb_tags', $names);
        $this->assertSame($names, array_values(array_unique($names)));
    }

    public function test_it_reads_columns_with_their_metadata(): void
    {
        $columns = $this->driver->getColumns('laradb_posts');

        $this->assertSame(['id', 'tag_id', 'title', 'body'], array_map(static fn ($c): string => $c->name, $columns));
        $this->assertTrue($columns[0]->primaryKey);
        $this->assertFalse($columns[2]->primaryKey);
        $this->assertFalse($columns[2]->nullable);
        $this->assertTrue($columns[3]->nullable);
        $this->assertNotSame('', $columns[2]->type);
    }

    public function test_it_points_a_column_at_the_one_it_references(): void
    {
        $columns = $this->driver->getColumns('laradb_posts');

        $this->assertNull($columns[0]->foreignKey);
        $this->assertSame('laradb_tags.id', $columns[1]->foreignKey);
        $this->assertSame(['tag_id' => 'laradb_tags.id'], $this->driver->getForeignKeys('laradb_posts'));
        $this->assertSame([], $this->driver->getForeignKeys('laradb_tags'));
    }

    public function test_it_lists_the_columns_a_foreign_key_points_at(): void
    {
        $targets = $this->driver->foreignKeyTargets();

        // laradb_posts.tag_id references laradb_tags.id, and that is the only
        // foreign key in the fixture.
        $this->assertArrayHasKey('laradb_tags', $targets);
        $this->assertSame(['id'], $targets['laradb_tags']);
        $this->assertArrayNotHasKey('laradb_posts', $targets);
    }

    public function test_it_filters_to_the_row_a_foreign_key_points_at(): void
    {
        $this->pdo->exec("INSERT INTO laradb_tags (label) VALUES ('ops'), ('infra')");

        $driver = $this->makeDriver($this->pdo);
        $first = (string) $driver->getRows('laradb_tags', 1, 25)->rows[0]['id'];

        $page = $driver->getRows('laradb_tags', 1, 25, new RowFilter('id', $first));

        $this->assertSame(1, $page->total);
        $this->assertSame($first, (string) $page->rows[0]['id']);
        $this->assertStringContainsString('WHERE', $page->sql);
        $this->assertStringNotContainsString((string) $first, explode('WHERE', $page->sql)[1]);
    }

    public function test_it_refuses_to_filter_on_a_column_nothing_references(): void
    {
        $this->expectException(UnknownColumnException::class);

        $this->driver->getRows('laradb_posts', 1, 25, new RowFilter('title', 'Post 1'));
    }

    public function test_it_describes_the_database(): void
    {
        $info = $this->driver->describe();

        $this->assertSame($this->expectedEngineName(), $info->engine);

        // Versions, sizes and index counts depend on the server the suite
        // happens to be pointed at, so only their shape is asserted.
        $this->assertNotNull($info->version);
        $this->assertMatchesRegularExpression('/^\d+\./', $info->version);
        $this->assertNotNull($info->name);
        $this->assertNotSame('', $info->name);
        $this->assertGreaterThanOrEqual(2, $info->tableCount);
        $this->assertNotNull($info->indexCount);
        $this->assertGreaterThan(0, $info->indexCount);
        $this->assertNotNull($info->sizeInBytes);
        $this->assertGreaterThan(0, $info->sizeInBytes);
    }

    public function test_it_reports_the_engine_settings(): void
    {
        $metadata = $this->driver->metadata();

        foreach ($this->expectedMetadataKeys() as $key) {
            $this->assertArrayHasKey($key, $metadata);
            $this->assertNotSame('', $metadata[$key]);
        }
    }

    public function test_it_counts_rows_exactly(): void
    {
        $this->assertSame(7, $this->driver->getRowCount('laradb_posts'));
        $this->assertSame(0, $this->driver->getRowCount('laradb_tags'));
    }

    public function test_it_paginates_rows(): void
    {
        $page = $this->driver->getRows('laradb_posts', 2, 3);

        $this->assertStringContainsString('LIMIT 3 OFFSET 3', $page->sql);
        $this->assertGreaterThan(0.0, $page->durationMs);
        $this->assertSame(2, $page->page);
        $this->assertSame(7, $page->total);
        $this->assertSame(3, $page->lastPage());
        $this->assertCount(3, $page->rows);
        $this->assertArrayHasKey('title', $page->rows[0]);
    }

    public function test_it_clamps_an_out_of_range_page(): void
    {
        $this->assertSame(3, $this->driver->getRows('laradb_posts', 900, 3)->page);
    }

    public function test_an_empty_table_yields_an_empty_page(): void
    {
        $page = $this->driver->getRows('laradb_tags', 1, 25);

        $this->assertTrue($page->isEmpty());
        $this->assertSame(0, $page->total);
        $this->assertCount(2, $page->columns);
    }

    public function test_it_refuses_a_table_that_is_not_in_the_schema(): void
    {
        $this->expectException(UnknownTableException::class);

        $this->driver->getRows('laradb_posts; DROP TABLE laradb_posts', 1, 10);
    }

    protected static function env(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }
}

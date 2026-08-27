<?php

declare(strict_types=1);

namespace LaraDb\Tests\Integration;

use LaraDb\Drivers\AbstractDriver;
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

        $this->assertSame(['id', 'title', 'body'], array_map(static fn ($c): string => $c->name, $columns));
        $this->assertTrue($columns[0]->primaryKey);
        $this->assertFalse($columns[1]->primaryKey);
        $this->assertFalse($columns[1]->nullable);
        $this->assertTrue($columns[2]->nullable);
        $this->assertNotSame('', $columns[1]->type);
    }

    public function test_it_counts_rows_exactly(): void
    {
        $this->assertSame(7, $this->driver->getRowCount('laradb_posts'));
        $this->assertSame(0, $this->driver->getRowCount('laradb_tags'));
    }

    public function test_it_paginates_rows(): void
    {
        $page = $this->driver->getRows('laradb_posts', 2, 3);

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

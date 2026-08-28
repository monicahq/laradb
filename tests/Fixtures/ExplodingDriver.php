<?php

declare(strict_types=1);

namespace LaraDb\Tests\Fixtures;

use LaraDb\Drivers\AbstractDriver;
use LaraDb\DTO\RowFilter;
use LaraDb\DTO\TableInfo;
use LaraDb\DTO\TablePage;
use LaraDb\Exceptions\QueryFailedException;
use PDOException;

/**
 * A driver whose reads fail the way a real one does: with a PDO message that
 * names the statement and the schema it ran against.
 *
 * Provoking that through a live connection is awkward — a table that vanishes
 * is refused as unknown before any query runs — so the failure is staged here
 * instead, and the test asserts on what reaches the page.
 */
final class ExplodingDriver extends AbstractDriver
{
    /**
     * Shaped after a real PostgreSQL error, which quotes the relation and the
     * statement, and is exactly what must not be rendered.
     */
    public const LEAK = 'SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "payroll_2026" does not exist';

    public function name(): string
    {
        return 'sqlite';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"'.$identifier.'"';
    }

    protected function fetchTables(): array
    {
        return [new TableInfo('users', 3)];
    }

    protected function fetchColumns(string $table): array
    {
        return [];
    }

    protected function fetchForeignKeyTargets(): array
    {
        return [];
    }

    public function getRows(string $table, int $page, int $perPage, ?RowFilter $filter = null): TablePage
    {
        throw QueryFailedException::from(new PDOException(self::LEAK));
    }
}

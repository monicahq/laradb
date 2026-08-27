<?php

declare(strict_types=1);

namespace LaraDb\Drivers;

use LaraDb\DTO\ColumnInfo;
use LaraDb\DTO\TableInfo;
use LaraDb\DTO\TablePage;
use LaraDb\Exceptions\UnknownTableException;

/**
 * The read-only contract every database engine implements.
 *
 * This interface is the public API of the package: per semver, any change to
 * it requires a major version bump.
 *
 * Implementations must never accept a table name straight from user input.
 * `$table` is always validated against the schema introspection whitelist
 * before being quoted and interpolated into a statement.
 */
interface DriverInterface
{
    /**
     * Every base table of the current schema, alphabetically.
     *
     * @return list<TableInfo>
     */
    public function listTables(): array;

    /**
     * The columns of a table, in declaration order.
     *
     * @return list<ColumnInfo>
     *
     * @throws UnknownTableException when the table is not in the whitelist
     */
    public function getColumns(string $table): array;

    /**
     * The exact number of rows in a table.
     *
     * @throws UnknownTableException when the table is not in the whitelist
     */
    public function getRowCount(string $table): int;

    /**
     * One page of rows. `$page` is 1-indexed and clamped to the available
     * range, so an out-of-bounds page never produces a blank screen.
     *
     * @throws UnknownTableException when the table is not in the whitelist
     */
    public function getRows(string $table, int $page, int $perPage): TablePage;

    /**
     * The engine name this driver handles: `mysql`, `pgsql` or `sqlite`.
     */
    public function name(): string;
}

<?php

declare(strict_types=1);

namespace LaraDb\Drivers;

use LaraDb\DTO\ColumnInfo;
use LaraDb\DTO\DatabaseInfo;
use LaraDb\DTO\RowFilter;
use LaraDb\DTO\TableInfo;
use LaraDb\DTO\TablePage;
use LaraDb\Exceptions\UnknownColumnException;
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
 *
 * The introspection methods below — everything from serverVersion() down —
 * describe the database rather than read it. They exist for the chrome around
 * the rows, so they are all allowed to answer "I don't know": a connection
 * whose user cannot read the system catalogues must still get a working
 * viewer, and an implementation returning null there is behaving correctly.
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
     * The columns of a table, in declaration order, each carrying the foreign
     * key it participates in when there is one.
     *
     * @return list<ColumnInfo>
     *
     * @throws UnknownTableException when the table is not in the whitelist
     */
    public function getColumns(string $table): array;

    /**
     * The exact number of rows in a table, or of the rows a filter selects.
     *
     * @throws UnknownTableException when the table is not in the whitelist
     * @throws UnknownColumnException when the filter's column is not a
     *                                foreign key target
     */
    public function getRowCount(string $table, ?RowFilter $filter = null): int;

    /**
     * One page of rows. `$page` is 1-indexed and clamped to the available
     * range, so an out-of-bounds page never produces a blank screen.
     *
     * A filter narrows the page to the rows whose column equals its value —
     * this is what following a foreign key resolves to.
     *
     * @throws UnknownTableException when the table is not in the whitelist
     * @throws UnknownColumnException when the filter's column is not a
     *                                foreign key target
     */
    public function getRows(string $table, int $page, int $perPage, ?RowFilter $filter = null): TablePage;

    /**
     * The engine name this driver handles: `mysql`, `pgsql` or `sqlite`.
     */
    public function name(): string;

    /**
     * The version of the server on the other end of the connection.
     */
    public function serverVersion(): ?string;

    /**
     * The database being browsed: its name, or — for a file-backed engine —
     * the file it lives in. Never a DSN, and never anything carrying
     * credentials.
     */
    public function databaseName(): ?string;

    /**
     * How much disk the database occupies, tables and indexes together.
     */
    public function sizeInBytes(): ?int;

    /**
     * How many indexes exist in the schema being browsed.
     */
    public function indexCount(): ?int;

    /**
     * Engine-level settings worth showing, in display order: SQLite reports
     * its page size and journal mode, MySQL its storage engine and collation,
     * PostgreSQL its encoding and search schema.
     *
     * @return array<string, string>
     */
    public function metadata(): array;

    /**
     * The foreign keys declared on a table, as column name => `table.column`.
     *
     * @return array<string, string>
     *
     * @throws UnknownTableException when the table is not in the whitelist
     */
    public function getForeignKeys(string $table): array;

    /**
     * Every (table, column) that a foreign key somewhere in this schema points
     * at — the set of things it is legal to filter on, and therefore the set
     * of things a foreign key may be followed to.
     *
     * @return array<string, list<string>> table name => column names
     */
    public function foreignKeyTargets(): array;

    /**
     * Everything above, gathered once.
     */
    public function describe(): DatabaseInfo;

    /**
     * How many statements this driver has run since it was built. Cheap
     * feedback on what opening a page actually costs.
     */
    public function queryCount(): int;
}

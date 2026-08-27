<?php

declare(strict_types=1);

namespace LaraDb\Drivers;

use LaraDb\DTO\ColumnInfo;
use LaraDb\DTO\TableInfo;

/**
 * SQLite introspection, via `sqlite_master` and `PRAGMA table_info`.
 */
final class SqliteDriver extends AbstractDriver
{
    public function name(): string
    {
        return 'sqlite';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    protected function fetchTables(): array
    {
        $rows = $this->select(
            "SELECT name FROM sqlite_master
             WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
             ORDER BY name ASC"
        );

        // SQLite keeps no row statistics, and the databases it backs are
        // usually small enough that an exact count is affordable up front.
        return array_map(fn (array $row): TableInfo => new TableInfo(
            name: (string) $row['name'],
            approximateRowCount: $this->countTable((string) $row['name']),
        ), $rows);
    }

    public function getColumns(string $table): array
    {
        $this->assertTableExists($table);

        // PRAGMA does not accept bound parameters, so the name has to be
        // interpolated. assertTableExists() above guarantees it came from
        // sqlite_master, and quoteIdentifier() escapes the quotes.
        $rows = $this->select('PRAGMA table_info('.$this->quoteIdentifier($table).')');

        return array_map(static fn (array $row): ColumnInfo => new ColumnInfo(
            name: (string) $row['name'],
            type: (string) ($row['type'] ?? ''),
            nullable: (int) ($row['notnull'] ?? 0) === 0,
            primaryKey: (int) ($row['pk'] ?? 0) > 0,
            default: isset($row['dflt_value']) ? (string) $row['dflt_value'] : null,
        ), $rows);
    }

    /**
     * Count without going through assertTableExists(), which would recurse
     * while the whitelist is still being built.
     */
    private function countTable(string $table): int
    {
        $result = $this->selectOne('SELECT COUNT(*) AS aggregate FROM '.$this->quoteIdentifier($table));

        return (int) ($result['aggregate'] ?? 0);
    }
}

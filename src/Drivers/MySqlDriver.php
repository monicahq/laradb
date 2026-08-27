<?php

declare(strict_types=1);

namespace LaraDb\Drivers;

use LaraDb\DTO\ColumnInfo;
use LaraDb\DTO\TableInfo;

/**
 * MySQL / MariaDB introspection, via `information_schema`.
 */
final class MySqlDriver extends AbstractDriver
{
    public function name(): string
    {
        return 'mysql';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    protected function fetchTables(): array
    {
        $rows = $this->select(
            "SELECT TABLE_NAME AS name, TABLE_ROWS AS row_count
             FROM information_schema.tables
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME ASC"
        );

        // TABLE_ROWS is an estimate from the storage engine (and always NULL
        // on some of them). It is what keeps listing a large schema cheap;
        // getRowCount() is the exact figure used for pagination.
        return array_map(static fn (array $row): TableInfo => new TableInfo(
            name: (string) $row['name'],
            approximateRowCount: isset($row['row_count']) ? (int) $row['row_count'] : null,
        ), $rows);
    }

    public function getColumns(string $table): array
    {
        $this->assertTableExists($table);

        $rows = $this->select(
            'SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable,
                    COLUMN_KEY AS column_key, COLUMN_DEFAULT AS column_default
             FROM information_schema.columns
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
             ORDER BY ORDINAL_POSITION ASC',
            ['table' => $table],
        );

        return array_map(static fn (array $row): ColumnInfo => new ColumnInfo(
            name: (string) $row['name'],
            type: (string) ($row['type'] ?? ''),
            nullable: strtoupper((string) ($row['nullable'] ?? 'YES')) === 'YES',
            primaryKey: strtoupper((string) ($row['column_key'] ?? '')) === 'PRI',
            default: isset($row['column_default']) ? (string) $row['column_default'] : null,
        ), $rows);
    }
}

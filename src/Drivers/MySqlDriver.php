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

    protected function fetchColumns(string $table): array
    {
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

    protected function fetchForeignKeys(string $table): array
    {
        $rows = $this->select(
            'SELECT COLUMN_NAME AS name,
                    REFERENCED_TABLE_NAME AS referenced_table,
                    REFERENCED_COLUMN_NAME AS referenced_column
             FROM information_schema.key_column_usage
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY ORDINAL_POSITION ASC',
            ['table' => $table],
        );

        $references = [];

        foreach ($rows as $row) {
            $references[(string) $row['name']] = sprintf(
                '%s.%s',
                (string) $row['referenced_table'],
                (string) $row['referenced_column'],
            );
        }

        return $references;
    }

    public function serverVersion(): ?string
    {
        return $this->introspect(function (): ?string {
            $version = $this->scalar('SELECT VERSION()');

            if ($version === null) {
                return null;
            }

            // MariaDB prefixes its version with a fake "5.5.5-" so that old
            // clients keep talking to it. Drop that before anything else.
            $version = preg_replace('/^5\.5\.5-(?=\d)/', '', $version) ?? $version;

            // Distributions then append their own build suffix
            // ("8.4.0-0ubuntu0.24.04.1"); the number is the useful part.
            return explode('-', $version)[0];
        });
    }

    public function databaseName(): ?string
    {
        return $this->introspect(fn (): ?string => $this->scalar('SELECT DATABASE()'));
    }

    public function sizeInBytes(): ?int
    {
        return $this->introspect(fn (): ?int => $this->integer(
            'SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0)
             FROM information_schema.tables
             WHERE TABLE_SCHEMA = DATABASE()'
        ));
    }

    public function indexCount(): ?int
    {
        // Every index shows up once per column it covers, so the composite
        // ones have to be collapsed before counting.
        return $this->introspect(fn (): ?int => $this->integer(
            'SELECT COUNT(*) FROM (
                 SELECT DISTINCT TABLE_NAME, INDEX_NAME
                 FROM information_schema.statistics
                 WHERE TABLE_SCHEMA = DATABASE()
             ) AS laradb_index_names'
        ));
    }

    public function metadata(): array
    {
        return $this->introspect(fn (): array => array_filter([
            'engine' => $this->scalar('SELECT @@default_storage_engine'),
            'charset' => $this->scalar('SELECT @@character_set_database'),
            'collation' => $this->scalar('SELECT @@collation_database'),
        ], static fn (?string $value): bool => $value !== null && $value !== '')) ?? [];
    }
}

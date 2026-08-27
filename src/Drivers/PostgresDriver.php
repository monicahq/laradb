<?php

declare(strict_types=1);

namespace LaraDb\Drivers;

use LaraDb\DTO\ColumnInfo;
use LaraDb\DTO\TableInfo;

/**
 * PostgreSQL introspection, via `information_schema` and `pg_class`.
 *
 * Everything is scoped to `current_schema()` so the viewer shows the tables
 * the application itself resolves, whatever the connection's search_path is.
 */
final class PostgresDriver extends AbstractDriver
{
    public function name(): string
    {
        return 'pgsql';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    protected function fetchTables(): array
    {
        // reltuples is the planner's estimate, maintained by ANALYZE. It is
        // -1 on a table that has never been analysed (PG 14+), and 0 on older
        // versions, so we only trust a positive value.
        $rows = $this->select(
            "SELECT c.relname AS name, c.reltuples AS row_count
             FROM pg_class c
             INNER JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema() AND c.relkind = 'r'
             ORDER BY c.relname ASC"
        );

        return array_map(static function (array $row): TableInfo {
            $estimate = isset($row['row_count']) ? (int) $row['row_count'] : -1;

            return new TableInfo(
                name: (string) $row['name'],
                approximateRowCount: $estimate >= 0 ? $estimate : null,
            );
        }, $rows);
    }

    public function getColumns(string $table): array
    {
        $this->assertTableExists($table);

        $primaryKeys = $this->primaryKeyColumns($table);

        $rows = $this->select(
            'SELECT column_name AS name, data_type AS type, is_nullable AS nullable,
                    column_default AS column_default
             FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = :table
             ORDER BY ordinal_position ASC',
            ['table' => $table],
        );

        return array_map(static fn (array $row): ColumnInfo => new ColumnInfo(
            name: (string) $row['name'],
            type: (string) ($row['type'] ?? ''),
            nullable: strtoupper((string) ($row['nullable'] ?? 'YES')) === 'YES',
            primaryKey: in_array((string) $row['name'], $primaryKeys, true),
            default: isset($row['column_default']) ? (string) $row['column_default'] : null,
        ), $rows);
    }

    /**
     * @return list<string>
     */
    private function primaryKeyColumns(string $table): array
    {
        $rows = $this->select(
            "SELECT kcu.column_name AS name
             FROM information_schema.table_constraints tc
             INNER JOIN information_schema.key_column_usage kcu
                 ON kcu.constraint_name = tc.constraint_name
                 AND kcu.constraint_schema = tc.constraint_schema
             WHERE tc.table_schema = current_schema()
               AND tc.table_name = :table
               AND tc.constraint_type = 'PRIMARY KEY'",
            ['table' => $table],
        );

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }
}

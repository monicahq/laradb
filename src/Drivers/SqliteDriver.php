<?php

declare(strict_types=1);

namespace LaraDb\Drivers;

use LaraDb\DTO\ColumnInfo;
use LaraDb\DTO\TableInfo;

/**
 * SQLite introspection, via `sqlite_master`, `PRAGMA table_info` and friends.
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

    protected function fetchColumns(string $table): array
    {
        // PRAGMA does not accept bound parameters, so the name has to be
        // interpolated. getColumns() has already matched it against
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

    protected function fetchForeignKeys(string $table): array
    {
        $rows = $this->select('PRAGMA foreign_key_list('.$this->quoteIdentifier($table).')');

        $references = [];

        foreach ($rows as $row) {
            $target = (string) ($row['table'] ?? '');

            if ($target === '') {
                continue;
            }

            // `to` is NULL when the reference implicitly targets the primary
            // key of the other table. Naming the table alone is honest there;
            // resolving it would cost a PRAGMA per foreign key.
            $column = isset($row['to']) ? (string) $row['to'] : '';

            $references[(string) $row['from']] = $column === '' ? $target : $target.'.'.$column;
        }

        return $references;
    }

    public function serverVersion(): ?string
    {
        return $this->introspect(fn (): ?string => $this->scalar('SELECT sqlite_version()'));
    }

    /**
     * The file backing the database, exactly as SQLite reports it. Callers
     * displaying it are expected to shorten it: an absolute server path has no
     * business in a web page.
     */
    public function databaseName(): ?string
    {
        return $this->introspect(function (): ?string {
            foreach ($this->select('PRAGMA database_list') as $row) {
                if ((string) ($row['name'] ?? '') !== 'main') {
                    continue;
                }

                $file = (string) ($row['file'] ?? '');

                // An empty file means the database is not on disk at all.
                return $file === '' ? ':memory:' : $file;
            }

            return null;
        });
    }

    public function sizeInBytes(): ?int
    {
        return $this->introspect(function (): ?int {
            $pages = $this->integer('PRAGMA page_count');
            $pageSize = $this->integer('PRAGMA page_size');

            return $pages === null || $pageSize === null ? null : $pages * $pageSize;
        });
    }

    public function indexCount(): ?int
    {
        return $this->introspect(fn (): ?int => $this->integer(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name NOT LIKE 'sqlite_%'"
        ));
    }

    public function metadata(): array
    {
        return $this->introspect(function (): array {
            $schema = $this->integer('PRAGMA schema_version');
            $pageSize = $this->integer('PRAGMA page_size');
            $foreignKeys = $this->integer('PRAGMA foreign_keys');

            return array_filter([
                'page' => $pageSize === null ? null : $pageSize.' B',
                'journal' => $this->scalar('PRAGMA journal_mode'),
                'enc' => $this->scalar('PRAGMA encoding'),
                'fk' => $foreignKeys === null ? null : ($foreignKeys === 1 ? 'on' : 'off'),
                'schema' => $schema === null ? null : 'v'.$schema,
            ], static fn (?string $value): bool => $value !== null && $value !== '');
        }) ?? [];
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

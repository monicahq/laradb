<?php

declare(strict_types=1);

namespace LaraDb\Drivers;

use LaraDb\DTO\ColumnInfo;
use LaraDb\DTO\TableInfo;
use Throwable;

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
            // key of the other table. Resolve it, so the badge names a real
            // column and the link has something to filter on.
            $column = isset($row['to']) ? (string) $row['to'] : ($this->primaryKeyOf($target) ?? '');

            if ($column === '') {
                continue;
            }

            $references[(string) $row['from']] = $target.'.'.$column;
        }

        return $references;
    }

    protected function fetchForeignKeyTargets(): array
    {
        // pragma_foreign_key_list() as a table-valued function lets the whole
        // schema be read in one statement. It landed in SQLite 3.16 (2017);
        // older builds fall back to walking the tables.
        try {
            return $this->groupTargets($this->select(
                'SELECT DISTINCT f."table" AS target_table, f."to" AS target_column
                 FROM sqlite_master m
                 JOIN pragma_foreign_key_list(m.name) f
                 WHERE m.type = \'table\' AND m.name NOT LIKE \'sqlite_%\'
                   AND f."to" IS NOT NULL'
            ));
        } catch (Throwable) {
            return $this->targetsByWalkingTables();
        }
    }

    /**
     * The pre-3.16 path, and the one that also picks up references written
     * without a column, which the query above cannot express.
     *
     * @return array<string, list<string>>
     */
    private function targetsByWalkingTables(): array
    {
        $rows = [];

        foreach ($this->listTables() as $table) {
            foreach ($this->fetchForeignKeys($table->name) as $reference) {
                [$target, $column] = explode('.', $reference, 2);
                $rows[] = ['target_table' => $target, 'target_column' => $column];
            }
        }

        return $this->groupTargets($rows);
    }

    /**
     * The first primary key column of a table, for references that name a
     * table without naming a column.
     */
    private function primaryKeyOf(string $table): ?string
    {
        foreach ($this->select('PRAGMA table_info('.$this->quoteIdentifier($table).')') as $row) {
            if ((int) ($row['pk'] ?? 0) > 0) {
                return (string) $row['name'];
            }
        }

        return null;
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

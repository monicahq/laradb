<?php

declare(strict_types=1);

namespace LaraDb\Drivers;

use LaraDb\DTO\ColumnInfo;
use LaraDb\DTO\DatabaseInfo;
use LaraDb\DTO\RowFilter;
use LaraDb\DTO\TableInfo;
use LaraDb\DTO\TablePage;
use LaraDb\Exceptions\QueryFailedException;
use LaraDb\Exceptions\UnknownColumnException;
use LaraDb\Exceptions\UnknownTableException;
use LaraDb\Support\Bytes;
use PDO;
use PDOException;
use Throwable;

/**
 * Shared, engine-agnostic behaviour: whitelisting, pagination arithmetic and
 * value normalisation. Subclasses only supply the introspection SQL and the
 * identifier quoting rules of their engine.
 *
 * The class holds nothing but a PDO handle on purpose — the reading core has
 * no knowledge of Laravel, so a Symfony adapter can reuse it as is.
 */
abstract class AbstractDriver implements DriverInterface
{
    /**
     * Hard ceiling on the page size, whatever the caller asks for. Guards
     * against a config typo trying to render a million rows in one page.
     */
    public const MAX_PER_PAGE = 1000;

    /**
     * Memoised whitelist, so listing tables and then reading one of them does
     * not introspect the schema twice within a single request.
     *
     * @var list<TableInfo>|null
     */
    private ?array $tables = null;

    private ?DatabaseInfo $description = null;

    /**
     * Memoised set of legal filter columns, table => column names.
     *
     * @var array<string, list<string>>|null
     */
    private ?array $targets = null;

    private int $queries = 0;

    public function __construct(protected readonly PDO $pdo) {}

    /**
     * @return list<TableInfo>
     */
    public function listTables(): array
    {
        return $this->tables ??= $this->fetchTables();
    }

    /**
     * @return list<ColumnInfo>
     */
    final public function getColumns(string $table): array
    {
        $this->assertTableExists($table);

        $references = $this->getForeignKeys($table);

        return array_map(
            static fn (ColumnInfo $column): ColumnInfo => $column->referencing($references[$column->name] ?? null),
            $this->fetchColumns($table),
        );
    }

    public function getRowCount(string $table, ?RowFilter $filter = null): int
    {
        $this->assertTableExists($table);

        $result = $this->selectOne(
            'SELECT COUNT(*) AS aggregate FROM '.$this->quoteIdentifier($table).$this->whereClause($table, $filter),
            $this->bindings($filter),
        );

        return (int) ($result['aggregate'] ?? 0);
    }

    public function getRows(string $table, int $page, int $perPage, ?RowFilter $filter = null): TablePage
    {
        $this->assertTableExists($table);

        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $total = $this->getRowCount($table, $filter);
        $lastPage = max(1, (int) ceil($total / $perPage));

        // Clamping rather than erroring: a stale bookmark pointing at page 900
        // of a table that shrank should still render something useful.
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // $perPage and $offset are integers derived from arithmetic above; the
        // table name has just been matched against the schema whitelist, and
        // whereClause() only ever interpolates a column the schema vouched
        // for. The filter's *value* is bound, so this statement still carries
        // no caller-controlled string.
        $sql = sprintf(
            'SELECT * FROM %s%s LIMIT %d OFFSET %d',
            $this->quoteIdentifier($table),
            $this->whereClause($table, $filter),
            $perPage,
            $offset,
        );

        // Timed so the UI can show what the page cost. This is the read the
        // visitor is waiting on; the introspection around it is memoised.
        $startedAt = hrtime(true);
        $rows = $this->select($sql, $this->bindings($filter));
        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;

        return new TablePage(
            table: $table,
            columns: $this->getColumns($table),
            rows: array_map(fn (array $row): array => $this->normaliseRow($row), $rows),
            page: $page,
            perPage: $perPage,
            total: $total,
            sql: $sql,
            durationMs: $durationMs,
            filter: $filter,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public function foreignKeyTargets(): array
    {
        return $this->targets ??= $this->introspect(fn (): array => $this->fetchForeignKeyTargets()) ?? [];
    }

    public function serverVersion(): ?string
    {
        return $this->introspect(function (): ?string {
            /** @var mixed $version */
            $version = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

            return is_scalar($version) ? (string) $version : null;
        });
    }

    public function databaseName(): ?string
    {
        return null;
    }

    public function sizeInBytes(): ?int
    {
        return null;
    }

    public function indexCount(): ?int
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function metadata(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function getForeignKeys(string $table): array
    {
        $this->assertTableExists($table);

        return $this->introspect(fn (): array => $this->fetchForeignKeys($table)) ?? [];
    }

    public function describe(): DatabaseInfo
    {
        return $this->description ??= new DatabaseInfo(
            engine: $this->name(),
            version: $this->serverVersion(),
            name: $this->databaseName(),
            tableCount: count($this->listTables()),
            indexCount: $this->indexCount(),
            sizeInBytes: $this->sizeInBytes(),
            metadata: $this->metadata(),
        );
    }

    public function queryCount(): int
    {
        return $this->queries;
    }

    /**
     * Engine-specific table introspection.
     *
     * @return list<TableInfo>
     */
    abstract protected function fetchTables(): array;

    /**
     * Engine-specific column introspection. Called only for a table that has
     * already been matched against the whitelist; foreign keys are grafted on
     * by getColumns(), so implementations do not have to look them up.
     *
     * @return list<ColumnInfo>
     */
    abstract protected function fetchColumns(string $table): array;

    /**
     * Engine-specific foreign key introspection, as column => `table.column`.
     *
     * @return array<string, string>
     */
    protected function fetchForeignKeys(string $table): array
    {
        return [];
    }

    /**
     * Quote a table or column name according to the engine's rules. The inner
     * quote character is doubled, which is how every engine we support escapes
     * it, so a table named `we"ird` cannot break out of the identifier.
     */
    abstract public function quoteIdentifier(string $identifier): string;

    /**
     * Guarantee the name we are about to interpolate came from the schema and
     * not from the query string.
     *
     * @throws UnknownTableException
     */
    protected function assertTableExists(string $table): void
    {
        foreach ($this->listTables() as $known) {
            if ($known->name === $table) {
                return;
            }
        }

        throw UnknownTableException::forTable($table);
    }

    /**
     * Guarantee the column we are about to interpolate is one a foreign key
     * actually points at.
     *
     * Narrower than "a column of this table" on purpose: following a foreign
     * key is the only thing that needs this, so it is the only thing allowed
     * to use it. A URL naming any other column gets nowhere.
     *
     * @throws UnknownColumnException
     */
    protected function assertFilterable(string $table, string $column): void
    {
        if (in_array($column, $this->foreignKeyTargets()[$table] ?? [], true)) {
            return;
        }

        throw UnknownColumnException::forColumn($table, $column);
    }

    /**
     * The WHERE clause for a filter, or an empty string when there is none.
     * The column is validated first; the value is left to bindings().
     */
    private function whereClause(string $table, ?RowFilter $filter): string
    {
        if ($filter === null) {
            return '';
        }

        $this->assertFilterable($table, $filter->column);

        return ' WHERE '.$this->quoteIdentifier($filter->column).' = :value';
    }

    /**
     * @return array<string, scalar|null>
     */
    private function bindings(?RowFilter $filter): array
    {
        return $filter === null ? [] : ['value' => $filter->value];
    }

    /**
     * Engine-specific lookup of every foreign key target in the schema, as
     * table => column names. One query, not one per table.
     *
     * @return array<string, list<string>>
     */
    abstract protected function fetchForeignKeyTargets(): array;

    /**
     * Fold rows of `target_table` / `target_column` into the map
     * foreignKeyTargets() returns, dropping blanks and duplicates.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<string>>
     */
    protected function groupTargets(array $rows): array
    {
        $targets = [];

        foreach ($rows as $row) {
            $table = (string) ($row['target_table'] ?? '');
            $column = (string) ($row['target_column'] ?? '');

            if ($table === '' || $column === '') {
                continue;
            }

            if (! in_array($column, $targets[$table] ?? [], true)) {
                $targets[$table][] = $column;
            }
        }

        return $targets;
    }

    /**
     * Run a probe whose answer is decoration rather than content.
     *
     * Reading a system catalogue can fail for reasons that have nothing to do
     * with the rows the visitor asked for — a missing grant, a catalogue that
     * moved between releases. None of those should cost them the page, so the
     * failure becomes a null and the UI leaves that slot out.
     *
     * @template T
     *
     * @param  callable(): T  $probe
     * @return T|null
     */
    protected function introspect(callable $probe): mixed
    {
        try {
            return $probe();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The first column of the first row, as a string.
     */
    protected function scalar(string $sql): ?string
    {
        $row = $this->selectOne($sql);

        if ($row === null) {
            return null;
        }

        /** @var mixed $value */
        $value = array_values($row)[0] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * The first column of the first row, as an integer, or null when the
     * engine answered with nothing.
     */
    protected function integer(string $sql): ?int
    {
        $value = $this->scalar($sql);

        return $value === null || ! is_numeric($value) ? null : (int) $value;
    }

    /**
     * @param  array<string, scalar|null>  $bindings
     * @return list<array<string, mixed>>
     */
    protected function select(string $sql, array $bindings = []): array
    {
        $this->queries++;

        try {
            $statement = $this->pdo->prepare($sql);

            if ($statement === false) {
                throw new PDOException('Unable to prepare the statement.');
            }

            $statement->execute($bindings);

            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            return $rows;
        } catch (Throwable $e) {
            throw QueryFailedException::from($e);
        }
    }

    /**
     * @param  array<string, scalar|null>  $bindings
     * @return array<string, mixed>|null
     */
    protected function selectOne(string $sql, array $bindings = []): ?array
    {
        return $this->select($sql, $bindings)[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normaliseRow(array $row): array
    {
        return array_map(fn (mixed $value): mixed => $this->normaliseValue($value), $row);
    }

    /**
     * Make a raw column value safe to hand to both Blade and json_encode.
     *
     * Blobs come back as streams on some drivers and as binary strings on
     * others; neither survives a UTF-8 JSON encode, and dumping them into HTML
     * is useless anyway. We replace them with a size marker instead.
     */
    protected function normaliseValue(mixed $value): mixed
    {
        if (is_resource($value)) {
            $contents = stream_get_contents($value);
            $value = $contents === false ? '' : $contents;
        }

        if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
            return sprintf('[binary, %s]', Bytes::format(strlen($value)));
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace LaraDb\Drivers;

use LaraDb\DTO\TableInfo;
use LaraDb\DTO\TablePage;
use LaraDb\Exceptions\QueryFailedException;
use LaraDb\Exceptions\UnknownTableException;
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

    public function __construct(protected readonly PDO $pdo) {}

    /**
     * @return list<TableInfo>
     */
    public function listTables(): array
    {
        return $this->tables ??= $this->fetchTables();
    }

    public function getRowCount(string $table): int
    {
        $this->assertTableExists($table);

        $result = $this->selectOne('SELECT COUNT(*) AS aggregate FROM '.$this->quoteIdentifier($table));

        return (int) ($result['aggregate'] ?? 0);
    }

    public function getRows(string $table, int $page, int $perPage): TablePage
    {
        $this->assertTableExists($table);

        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $total = $this->getRowCount($table);
        $lastPage = max(1, (int) ceil($total / $perPage));

        // Clamping rather than erroring: a stale bookmark pointing at page 900
        // of a table that shrank should still render something useful.
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // $perPage and $offset are integers derived from arithmetic above, and
        // the table name has just been matched against the schema whitelist,
        // so this statement carries no caller-controlled string.
        $rows = $this->select(sprintf(
            'SELECT * FROM %s LIMIT %d OFFSET %d',
            $this->quoteIdentifier($table),
            $perPage,
            $offset,
        ));

        return new TablePage(
            table: $table,
            columns: $this->getColumns($table),
            rows: array_map(fn (array $row): array => $this->normaliseRow($row), $rows),
            page: $page,
            perPage: $perPage,
            total: $total,
        );
    }

    /**
     * Engine-specific table introspection.
     *
     * @return list<TableInfo>
     */
    abstract protected function fetchTables(): array;

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
     * @param  array<string, scalar|null>  $bindings
     * @return list<array<string, mixed>>
     */
    protected function select(string $sql, array $bindings = []): array
    {
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
            return sprintf('[binary, %s]', $this->formatBytes(strlen($value)));
        }

        return $value;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1024 / 1024, 1).' MB';
    }
}

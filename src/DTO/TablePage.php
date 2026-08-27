<?php

declare(strict_types=1);

namespace LaraDb\DTO;

/**
 * One page of rows for a given table, plus everything the UI needs to render
 * the pagination controls.
 */
final class TablePage
{
    /**
     * @param  list<ColumnInfo>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  string  $sql  the statement that produced these rows, shown in
     *                       the UI so the reader knows exactly what was run
     * @param  float  $durationMs  how long that statement took
     * @param  RowFilter|null  $filter  the filter that narrowed this page, if any
     */
    public function __construct(
        public readonly string $table,
        public readonly array $columns,
        public readonly array $rows,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
        public readonly string $sql = '',
        public readonly float $durationMs = 0.0,
        public readonly ?RowFilter $filter = null,
    ) {}

    /**
     * The primary key of the table, as a comma-separated list of column names,
     * or null when the table has none.
     */
    public function primaryKey(): ?string
    {
        $names = [];

        foreach ($this->columns as $column) {
            if ($column->primaryKey) {
                $names[] = $column->name;
            }
        }

        return $names === [] ? null : implode(', ', $names);
    }

    public function lastPage(): int
    {
        if ($this->perPage < 1) {
            return 1;
        }

        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->lastPage();
    }

    /**
     * 1-indexed position of the first row of this page, or null when empty.
     */
    public function from(): ?int
    {
        if ($this->rows === []) {
            return null;
        }

        return ($this->page - 1) * $this->perPage + 1;
    }

    /**
     * 1-indexed position of the last row of this page, or null when empty.
     */
    public function to(): ?int
    {
        if ($this->rows === []) {
            return null;
        }

        return ($this->page - 1) * $this->perPage + count($this->rows);
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * A condensed list of page numbers to render, with null standing for an
     * ellipsis. Keeps the pagination bar short on tables with many pages.
     *
     * @return list<int|null>
     */
    public function paginationWindow(int $each = 2): array
    {
        $last = $this->lastPage();

        if ($last <= (($each * 2) + 5)) {
            return range(1, $last);
        }

        $window = [1];
        $start = max(2, $this->page - $each);
        $end = min($last - 1, $this->page + $each);

        if ($start > 2) {
            $window[] = null;
        }

        for ($page = $start; $page <= $end; $page++) {
            $window[] = $page;
        }

        if ($end < $last - 1) {
            $window[] = null;
        }

        $window[] = $last;

        return $window;
    }

    /**
     * @return array{
     *     table: string,
     *     columns: list<array{name: string, type: string, nullable: bool, primary_key: bool, default: string|null, foreign_key: string|null}>,
     *     rows: list<array<string, mixed>>,
     *     page: int,
     *     per_page: int,
     *     total: int,
     *     last_page: int,
     *     sql: string,
     *     duration_ms: float,
     *     filter: array{column: string, value: string}|null
     * }
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'columns' => array_map(static fn (ColumnInfo $column): array => $column->toArray(), $this->columns),
            'rows' => $this->rows,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'last_page' => $this->lastPage(),
            'sql' => $this->sql,
            'duration_ms' => $this->durationMs,
            'filter' => $this->filter?->toArray(),
        ];
    }
}

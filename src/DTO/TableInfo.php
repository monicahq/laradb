<?php

declare(strict_types=1);

namespace LaraDb\DTO;

/**
 * A single table as returned by the schema introspection.
 *
 * The row count carried here is deliberately *approximate*: it comes from the
 * engine statistics (`information_schema.tables`, `pg_class.reltuples`, ...) so
 * that listing a schema with hundreds of tables stays cheap. Use
 * DriverInterface::getRowCount() when an exact number is required.
 */
final class TableInfo
{
    public function __construct(
        public readonly string $name,
        public readonly ?int $approximateRowCount = null,
    ) {}

    /**
     * @return array{name: string, approximate_row_count: int|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'approximate_row_count' => $this->approximateRowCount,
        ];
    }
}

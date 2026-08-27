<?php

declare(strict_types=1);

namespace LaraDb\DTO;

/**
 * An equality filter on one column: the thing that turns "browse this table"
 * into "show me the row this foreign key points at".
 *
 * The column is an identifier and ends up interpolated into the statement, so
 * it is validated against the schema before it gets there. The value never is:
 * it is bound, always, whatever it contains.
 */
final class RowFilter
{
    public function __construct(
        public readonly string $column,
        public readonly string $value,
    ) {}

    /**
     * @return array{column: string, value: string}
     */
    public function toArray(): array
    {
        return [
            'column' => $this->column,
            'value' => $this->value,
        ];
    }
}

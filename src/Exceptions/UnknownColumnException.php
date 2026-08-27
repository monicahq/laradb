<?php

declare(strict_types=1);

namespace LaraDb\Exceptions;

/**
 * Thrown when a filter names a column that no foreign key in the schema
 * points at.
 *
 * This is the sibling of UnknownTableException, and it exists for the same
 * reason: the column name in a WHERE clause is an identifier, so it has to be
 * interpolated, so it may only ever come from introspection. Restricting it to
 * declared foreign key targets — rather than to any column of the table —
 * keeps the surface as small as the feature needs it to be.
 */
final class UnknownColumnException extends LaraDbException
{
    public static function forColumn(string $table, string $column): self
    {
        return new self(sprintf('Column [%s.%s] is not a foreign key target.', $table, $column));
    }
}

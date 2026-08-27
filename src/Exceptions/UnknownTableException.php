<?php

declare(strict_types=1);

namespace LaraDb\Exceptions;

/**
 * Thrown when a table name does not appear in the schema introspection.
 *
 * This is the guard that keeps user input out of our SQL: every table name is
 * matched against the whitelist returned by the driver before it is quoted and
 * interpolated into a statement.
 */
final class UnknownTableException extends LaraDbException
{
    public static function forTable(string $table): self
    {
        return new self(sprintf('Unknown table [%s].', $table));
    }
}

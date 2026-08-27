<?php

declare(strict_types=1);

namespace LaraDb\Exceptions;

use Throwable;

/**
 * Thrown when the configured connection cannot be opened at all.
 *
 * The message is deliberately generic: the underlying PDO error often quotes
 * the DSN and the database user, and none of that belongs on a page whose
 * whole point is to be looked at. The real cause stays available through
 * getPrevious(), which is what gets reported to the log.
 */
final class ConnectionFailedException extends LaraDbException
{
    public static function forConnection(string $connection, Throwable $previous): self
    {
        return new self(
            sprintf('Could not open the [%s] database connection.', $connection),
            0,
            $previous,
        );
    }
}

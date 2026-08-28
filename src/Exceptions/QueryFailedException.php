<?php

declare(strict_types=1);

namespace LaraDb\Exceptions;

use Throwable;

/**
 * Wraps a PDO failure so the UI can render a readable error instead of a blank
 * page.
 *
 * The message is deliberately generic, for the same reason
 * ConnectionFailedException's is: a PDO error quotes the statement that failed,
 * the schema it ran against and, on PostgreSQL, the value that broke it. That
 * is a description of the database written for the developer, and it is
 * reachable from the query string — a filter value of the wrong type is enough
 * to provoke one. The real message stays on getPrevious(), which is what the
 * controller reports to the log.
 */
final class QueryFailedException extends LaraDbException
{
    public static function from(Throwable $previous): self
    {
        return new self('The database query could not be executed.', 0, $previous);
    }
}

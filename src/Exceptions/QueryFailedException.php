<?php

declare(strict_types=1);

namespace LaraDb\Exceptions;

use Throwable;

/**
 * Wraps a PDO failure so the UI can render a readable error instead of a blank
 * page, without leaking the connection credentials held by PDOException.
 */
final class QueryFailedException extends LaraDbException
{
    public static function from(Throwable $previous): self
    {
        return new self('The database query could not be executed: '.$previous->getMessage(), 0, $previous);
    }
}

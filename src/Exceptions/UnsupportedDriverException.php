<?php

declare(strict_types=1);

namespace LaraDb\Exceptions;

/**
 * Thrown when the configured connection uses a database engine LaraDb does not
 * know how to introspect.
 */
final class UnsupportedDriverException extends LaraDbException
{
    /**
     * @param  list<string>  $supported
     */
    public static function forDriver(string $driver, array $supported): self
    {
        return new self(sprintf(
            'Unsupported database driver [%s]. LaraDb supports: %s.',
            $driver,
            implode(', ', $supported),
        ));
    }
}

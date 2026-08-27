<?php

declare(strict_types=1);

namespace LaraDb;

use LaraDb\Drivers\DriverInterface;
use LaraDb\Drivers\MySqlDriver;
use LaraDb\Drivers\PostgresDriver;
use LaraDb\Drivers\SqliteDriver;
use LaraDb\Exceptions\UnsupportedDriverException;
use PDO;

/**
 * Resolves the right driver for a connection.
 *
 * Takes a plain PDO handle rather than a Laravel connection so the reading
 * core stays usable outside Laravel.
 */
final class DriverFactory
{
    /**
     * Engine aliases, mapped to the driver that handles them.
     *
     * @var array<string, class-string<DriverInterface>>
     */
    private const DRIVERS = [
        'mysql' => MySqlDriver::class,
        'mariadb' => MySqlDriver::class,
        'pgsql' => PostgresDriver::class,
        'postgres' => PostgresDriver::class,
        'postgresql' => PostgresDriver::class,
        'sqlite' => SqliteDriver::class,
        'sqlite3' => SqliteDriver::class,
    ];

    /**
     * @throws UnsupportedDriverException
     */
    public static function make(PDO $pdo, string $driver): DriverInterface
    {
        $driver = strtolower(trim($driver));

        if (! isset(self::DRIVERS[$driver])) {
            throw UnsupportedDriverException::forDriver($driver, self::supported());
        }

        $class = self::DRIVERS[$driver];

        return new $class($pdo);
    }

    /**
     * Resolve the engine from the handle itself, when the caller has no
     * configuration to read it from.
     *
     * @throws UnsupportedDriverException
     */
    public static function fromPdo(PDO $pdo): DriverInterface
    {
        /** @var string $driver */
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return self::make($pdo, $driver);
    }

    public static function supports(string $driver): bool
    {
        return isset(self::DRIVERS[strtolower(trim($driver))]);
    }

    /**
     * The canonical engine names, for error messages and documentation.
     *
     * @return list<string>
     */
    public static function supported(): array
    {
        return ['mysql', 'pgsql', 'sqlite'];
    }
}

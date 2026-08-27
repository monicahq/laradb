<?php

declare(strict_types=1);

namespace LaraDb\Tests\Unit;

use LaraDb\DriverFactory;
use LaraDb\Drivers\MySqlDriver;
use LaraDb\Drivers\PostgresDriver;
use LaraDb\Drivers\SqliteDriver;
use LaraDb\Exceptions\UnsupportedDriverException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DriverFactoryTest extends TestCase
{
    private function pdo(): PDO
    {
        return new PDO('sqlite::memory:');
    }

    /**
     * @param  class-string  $expected
     */
    #[DataProvider('engines')]
    public function test_it_resolves_the_driver_for_each_supported_engine(string $engine, string $expected): void
    {
        $this->assertInstanceOf($expected, DriverFactory::make($this->pdo(), $engine));
    }

    /**
     * @return array<string, array{string, class-string}>
     */
    public static function engines(): array
    {
        return [
            'mysql' => ['mysql', MySqlDriver::class],
            'mariadb' => ['mariadb', MySqlDriver::class],
            'pgsql' => ['pgsql', PostgresDriver::class],
            'postgres' => ['postgres', PostgresDriver::class],
            'postgresql' => ['postgresql', PostgresDriver::class],
            'sqlite' => ['sqlite', SqliteDriver::class],
            'sqlite3' => ['sqlite3', SqliteDriver::class],
            'uppercase' => ['MySQL', MySqlDriver::class],
            'padded' => ['  sqlite  ', SqliteDriver::class],
        ];
    }

    public function test_it_resolves_the_engine_from_the_handle_itself(): void
    {
        $this->assertInstanceOf(SqliteDriver::class, DriverFactory::fromPdo($this->pdo()));
    }

    public function test_it_rejects_an_unsupported_engine(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('Unsupported database driver [sqlsrv]');

        DriverFactory::make($this->pdo(), 'sqlsrv');
    }

    public function test_it_reports_what_it_supports(): void
    {
        $this->assertTrue(DriverFactory::supports('pgsql'));
        $this->assertFalse(DriverFactory::supports('mongodb'));
        $this->assertSame(['mysql', 'pgsql', 'sqlite'], DriverFactory::supported());
    }
}

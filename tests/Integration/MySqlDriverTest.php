<?php

declare(strict_types=1);

namespace LaraDb\Tests\Integration;

use LaraDb\Drivers\AbstractDriver;
use LaraDb\Drivers\MySqlDriver;
use PDO;

final class MySqlDriverTest extends IntegrationTestCase
{
    protected function connect(): PDO
    {
        return new PDO(
            self::env('LARADB_MYSQL_DSN', 'mysql:host=127.0.0.1;port=3306;dbname=laradb_test'),
            self::env('LARADB_MYSQL_USERNAME', 'root'),
            self::env('LARADB_MYSQL_PASSWORD', 'password'),
        );
    }

    protected function makeDriver(PDO $pdo): AbstractDriver
    {
        return new MySqlDriver($pdo);
    }

    protected function schemaStatements(): array
    {
        return [
            'CREATE TABLE laradb_posts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                body TEXT NULL
            )',
            'CREATE TABLE laradb_tags (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(255) NULL
            )',
        ];
    }

    protected function expectedEngineName(): string
    {
        return 'mysql';
    }

    protected function expectedQuotedIdentifier(): string
    {
        return '`laradb_posts`';
    }
}

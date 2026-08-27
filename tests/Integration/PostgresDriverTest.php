<?php

declare(strict_types=1);

namespace LaraDb\Tests\Integration;

use LaraDb\Drivers\AbstractDriver;
use LaraDb\Drivers\PostgresDriver;
use PDO;

final class PostgresDriverTest extends IntegrationTestCase
{
    protected function connect(): PDO
    {
        return new PDO(
            self::env('LARADB_PGSQL_DSN', 'pgsql:host=127.0.0.1;port=5432;dbname=laradb_test'),
            self::env('LARADB_PGSQL_USERNAME', 'postgres'),
            self::env('LARADB_PGSQL_PASSWORD', 'password'),
        );
    }

    protected function makeDriver(PDO $pdo): AbstractDriver
    {
        return new PostgresDriver($pdo);
    }

    protected function schemaStatements(): array
    {
        return [
            'CREATE TABLE laradb_tags (
                id SERIAL PRIMARY KEY,
                label VARCHAR(255) NULL
            )',
            'CREATE TABLE laradb_posts (
                id SERIAL PRIMARY KEY,
                tag_id INTEGER NULL REFERENCES laradb_tags (id),
                title VARCHAR(255) NOT NULL,
                body TEXT NULL
            )',
            'CREATE INDEX laradb_posts_title_index ON laradb_posts (title)',
        ];
    }

    protected function expectedEngineName(): string
    {
        return 'pgsql';
    }

    protected function expectedQuotedIdentifier(): string
    {
        return '"laradb_posts"';
    }

    /**
     * @return list<string>
     */
    protected function expectedMetadataKeys(): array
    {
        return ['enc', 'collation', 'schema'];
    }
}

<?php

declare(strict_types=1);

namespace LaraDb\Tests\Fixtures;

use LaraDb\Drivers\AbstractDriver;
use LaraDb\DTO\TableInfo;
use RuntimeException;

/**
 * A driver whose every introspection probe is refused, standing in for a
 * connection whose user cannot read the system catalogues.
 */
final class UnprivilegedDriver extends AbstractDriver
{
    public function name(): string
    {
        return 'unprivileged';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"'.$identifier.'"';
    }

    protected function fetchTables(): array
    {
        return [new TableInfo('posts')];
    }

    protected function fetchColumns(string $table): array
    {
        return [];
    }

    protected function fetchForeignKeys(string $table): array
    {
        throw new RuntimeException('SELECT command denied.');
    }

    protected function fetchForeignKeyTargets(): array
    {
        throw new RuntimeException('SELECT command denied.');
    }

    public function serverVersion(): ?string
    {
        return $this->introspect(fn (): string => throw new RuntimeException('SELECT command denied.'));
    }

    public function databaseName(): ?string
    {
        return $this->introspect(fn (): string => throw new RuntimeException('SELECT command denied.'));
    }

    public function sizeInBytes(): ?int
    {
        return $this->introspect(fn (): int => throw new RuntimeException('SELECT command denied.'));
    }

    public function indexCount(): ?int
    {
        return $this->introspect(fn (): int => throw new RuntimeException('SELECT command denied.'));
    }

    public function metadata(): array
    {
        return $this->introspect(fn (): array => throw new RuntimeException('SELECT command denied.')) ?? [];
    }
}

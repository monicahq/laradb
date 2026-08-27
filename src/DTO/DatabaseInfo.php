<?php

declare(strict_types=1);

namespace LaraDb\DTO;

use LaraDb\Support\Bytes;

/**
 * What the viewer knows about the database as a whole, as opposed to any one
 * table: the engine behind it, how big it is, and the handful of engine-level
 * settings worth putting in front of a developer.
 *
 * Everything but the engine name is nullable. Introspecting a database is a
 * privilege, not a right: a connection whose user cannot read
 * `information_schema` still gets a working viewer, just a quieter one.
 */
final class DatabaseInfo
{
    /**
     * @param  array<string, string>  $metadata  engine settings, in display order
     */
    public function __construct(
        public readonly string $engine,
        public readonly ?string $version = null,
        public readonly ?string $name = null,
        public readonly int $tableCount = 0,
        public readonly ?int $indexCount = null,
        public readonly ?int $sizeInBytes = null,
        public readonly array $metadata = [],
    ) {}

    public function formattedSize(): ?string
    {
        return $this->sizeInBytes === null ? null : Bytes::format($this->sizeInBytes, 2);
    }

    /**
     * @return array{
     *     engine: string,
     *     version: string|null,
     *     name: string|null,
     *     table_count: int,
     *     index_count: int|null,
     *     size_in_bytes: int|null,
     *     metadata: array<string, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'engine' => $this->engine,
            'version' => $this->version,
            'name' => $this->name,
            'table_count' => $this->tableCount,
            'index_count' => $this->indexCount,
            'size_in_bytes' => $this->sizeInBytes,
            'metadata' => $this->metadata,
        ];
    }
}

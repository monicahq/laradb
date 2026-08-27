<?php

declare(strict_types=1);

namespace LaraDb\DTO;

/**
 * A single column of a table.
 */
final class ColumnInfo
{
    /**
     * @param  string|null  $foreignKey  the column this one points at, as
     *                                   `table.column`, or null when it is not
     *                                   part of a foreign key
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $nullable = true,
        public readonly bool $primaryKey = false,
        public readonly ?string $default = null,
        public readonly ?string $foreignKey = null,
    ) {}

    /**
     * The same column, pointed at `table.column`. The DTO is readonly, so the
     * driver builds a new one rather than mutating the introspection result.
     */
    public function referencing(?string $foreignKey): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            nullable: $this->nullable,
            primaryKey: $this->primaryKey,
            default: $this->default,
            foreignKey: $foreignKey,
        );
    }

    /**
     * @return array{name: string, type: string, nullable: bool, primary_key: bool, default: string|null, foreign_key: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'primary_key' => $this->primaryKey,
            'default' => $this->default,
            'foreign_key' => $this->foreignKey,
        ];
    }
}

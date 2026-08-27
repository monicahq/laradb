<?php

declare(strict_types=1);

namespace LaraDb\DTO;

/**
 * A single column of a table.
 */
final class ColumnInfo
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $nullable = true,
        public readonly bool $primaryKey = false,
        public readonly ?string $default = null,
    ) {}

    /**
     * @return array{name: string, type: string, nullable: bool, primary_key: bool, default: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'primary_key' => $this->primaryKey,
            'default' => $this->default,
        ];
    }
}

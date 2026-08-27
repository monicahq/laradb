<?php

declare(strict_types=1);

namespace LaraDb\Support;

/**
 * Turns a raw column value into something readable in an HTML table.
 *
 * Kept separate from the drivers so the presentation rules are not baked into
 * the reading core, and so the JSON endpoint can return untouched values.
 */
final class ValuePresenter
{
    public function __construct(private readonly int $maxLength = 120) {}

    public function isNull(mixed $value): bool
    {
        return $value === null;
    }

    /**
     * The string rendered in the cell, truncated when too long.
     */
    public function display(mixed $value): string
    {
        $string = $this->stringify($value);

        if ($this->maxLength > 0 && mb_strlen($string) > $this->maxLength) {
            return mb_substr($string, 0, $this->maxLength).'…';
        }

        return $string;
    }

    /**
     * The full value, used as the cell tooltip when it had to be truncated.
     */
    public function full(mixed $value): string
    {
        return $this->stringify($value);
    }

    public function isTruncated(mixed $value): bool
    {
        return $this->maxLength > 0 && mb_strlen($this->stringify($value)) > $this->maxLength;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '[unrepresentable]' : $encoded;
    }
}

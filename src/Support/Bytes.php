<?php

declare(strict_types=1);

namespace LaraDb\Support;

/**
 * Human-readable byte sizes.
 *
 * Shared by the binary-value marker the drivers put in a cell and by the
 * database size shown in the chrome, so the two never drift apart.
 */
final class Bytes
{
    public static function format(int $bytes, int $precision = 1): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, $precision).' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024, $precision).' MB';
        }

        return round($bytes / 1024 / 1024 / 1024, $precision).' GB';
    }
}

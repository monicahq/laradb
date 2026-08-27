<?php

declare(strict_types=1);

namespace LaraDb\Support;

/**
 * Shortens the name a driver reports for the database it is browsing.
 *
 * File-backed engines answer with an absolute path. `/var/www/releases/
 * 20260827114302/database/database.sqlite` tells the reader nothing they want
 * and tells anyone else looking over their shoulder how the server is laid
 * out, so it is cut down to the part that is actually about the project.
 *
 * Anything that is not a path — a MySQL or PostgreSQL database name — falls
 * through untouched.
 */
final class DatabasePath
{
    public static function shorten(?string $name, ?string $basePath = null): ?string
    {
        if ($name === null || $name === '' || ! self::looksLikeAPath($name)) {
            return $name;
        }

        $path = str_replace('\\', '/', $name);

        if ($basePath !== null && $basePath !== '') {
            $base = rtrim(str_replace('\\', '/', $basePath), '/').'/';

            if (str_starts_with($path, $base)) {
                return substr($path, strlen($base));
            }
        }

        // Outside the project: the file name alone is as much as we will say.
        return basename($path);
    }

    private static function looksLikeAPath(string $name): bool
    {
        return str_contains($name, '/') || str_contains($name, '\\');
    }
}

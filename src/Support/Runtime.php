<?php

declare(strict_types=1);

namespace LaraDb\Support;

use Composer\InstalledVersions;
use Illuminate\Contracts\Foundation\Application;
use OutOfBoundsException;

/**
 * The versions of the things rendering the page: the package, the interpreter
 * and the framework.
 *
 * Every one of them is nullable on purpose. None is load-bearing — they fill
 * the footer strip — and none is worth an exception when the viewer is running
 * somewhere that cannot report them.
 */
final class Runtime
{
    private const PACKAGE = 'monicahq/laradb';

    public function __construct(
        public readonly ?string $package,
        public readonly string $php,
        public readonly ?string $laravel,
    ) {}

    public static function detect(?object $app = null): self
    {
        return new self(
            package: self::packageVersion(),
            php: PHP_VERSION,
            laravel: $app instanceof Application ? $app->version() : null,
        );
    }

    /**
     * The installed version of the package, as Composer knows it: a tag
     * (`v1.4.2`), a branch (`dev-main`), or nothing at all when the autoloader
     * has no record of us.
     */
    private static function packageVersion(): ?string
    {
        if (! class_exists(InstalledVersions::class)) {
            return null;
        }

        try {
            if (! InstalledVersions::isInstalled(self::PACKAGE)) {
                return null;
            }

            $version = InstalledVersions::getPrettyVersion(self::PACKAGE);
        } catch (OutOfBoundsException) {
            return null;
        }

        if ($version === null || $version === '') {
            return null;
        }

        return ctype_digit($version[0]) ? 'v'.$version : $version;
    }
}

<?php

declare(strict_types=1);

namespace LaraDb\Exceptions;

use RuntimeException;

/**
 * Base class for every exception thrown by the package, so that consumers can
 * catch `LaraDbException` and be sure they are not swallowing anything else.
 */
class LaraDbException extends RuntimeException {}

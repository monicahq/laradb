<?php

declare(strict_types=1);

namespace LaraDb\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stands in for the authorisation middleware an application is expected to
 * put in front of the viewer.
 */
final class DenyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        abort(403, 'Nope.');
    }
}

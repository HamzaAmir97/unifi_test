<?php

namespace App\Http\Middleware;

use App\Mock\Errors\MockError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** FR-018: the control plane does not exist in production. */
class EnsureNotProduction
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction()) {
            throw MockError::notFound('Not found.');
        }

        return $next($request);
    }
}

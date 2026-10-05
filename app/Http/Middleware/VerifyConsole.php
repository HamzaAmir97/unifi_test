<?php

namespace App\Http\Middleware;

use App\Mock\Errors\MockError;
use App\Mock\Unverified;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The console id in the path must be the mock's (FR-006). The status is UNVERIFIED (G10).
 * The parameter is then removed so controllers never receive it.
 */
class VerifyConsole
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        if ($route->parameter('consoleId') !== config('unifi_mock.console_id')) {
            throw MockError::withStatus(Unverified::CONSOLE_MISMATCH_STATUS, 'Unknown console.');
        }
        $route->forgetParameter('consoleId');

        return $next($request);
    }
}

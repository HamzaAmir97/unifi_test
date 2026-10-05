<?php

namespace App\Http\Middleware;

use App\Mock\Errors\MockError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * X-API-KEY must equal the configured key (FR-005). An empty configured key refuses
 * everything: there is no "no key configured, so open" mode.
 */
class RequireApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('unifi_mock.api_key');
        $given = (string) $request->header('X-API-KEY', '');

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            throw MockError::unauthorized();
        }

        return $next($request);
    }
}

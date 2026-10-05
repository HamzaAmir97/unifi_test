<?php

namespace App\Http\Middleware;

use App\Mock\Clock\MockClock;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every now() inside the request agrees with the mock clock (R10). Cleared afterwards.
 */
class ApplyMockClock
{
    public function __construct(private readonly MockClock $clock) {}

    public function handle(Request $request, Closure $next): Response
    {
        $now = $this->clock->now();
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);

        try {
            return $next($request);
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }
}

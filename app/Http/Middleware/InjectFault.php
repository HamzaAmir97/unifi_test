<?php

namespace App\Http\Middleware;

use App\Mock\Errors\ErrorEnvelope;
use App\Mock\Faults\FaultInjector;
use App\Mock\Unverified;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after authentication (R11), so a missing key still answers 401 whatever faults are
 * queued. A matching rule delays, fails, or times out the request; then the rule's count
 * goes down and the rule disappears at zero.
 */
class InjectFault
{
    public function __construct(private readonly FaultInjector $faults) {}

    public function handle(Request $request, Closure $next): Response
    {
        $rule = $this->faults->consume($request->method(), $this->faults->relativePath($request->path()));
        if ($rule === null) {
            return $next($request);
        }

        RecordRequest::warn($request, 'fault_injected', ['match' => $rule->match]);

        if ($rule->delayMs > 0) {
            usleep($rule->delayMs * 1000);
        }
        if ($rule->timeout) {
            return ErrorEnvelope::response($request, Unverified::TIMEOUT_STATUS, Unverified::ERROR_CODES['timeout'], 'Injected timeout.');
        }
        if ($rule->status !== null) {
            return ErrorEnvelope::response($request, $rule->status, Unverified::ERROR_CODES['fault'], "Injected fault ({$rule->status}).");
        }

        return $next($request);
    }
}

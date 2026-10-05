<?php

namespace App\Http\Middleware;

use App\Mock\Clock\MockClock;
use App\Mock\Errors\ErrorEnvelope;
use App\Models\MockRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Outermost middleware (R11), registered globally so unknown paths are logged too.
 *
 * - mints the request id shared with the error envelope (S7);
 * - captures REAL time before anything else runs, so moving the mock clock never changes
 *   the log (S6);
 * - stores the API key masked, never in full (FR-020);
 * - keeps only the newest `unifi_mock.log_keep` rows;
 * - a logging failure never changes the response.
 */
class RecordRequest
{
    public const WARNINGS = 'unifi_mock.warnings';

    private const NOT_LOGGED = ['docs', 'openapi.json', 'favicon.ico', 'up', '/'];

    public static function warn(Request $request, string $type, array $detail = []): void
    {
        $warnings = $request->attributes->get(self::WARNINGS, []);
        $warnings[] = ['type' => $type] + $detail;
        $request->attributes->set(self::WARNINGS, $warnings);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(ErrorEnvelope::REQUEST_ID, (string) Str::uuid());
        $request->attributes->set(self::WARNINGS, []);
        $startedAt = MockClock::realNow();
        $t0 = hrtime(true);

        $response = $next($request);

        if (! in_array($request->path(), self::NOT_LOGGED, true)) {
            try {
                $this->record($request, $response, $startedAt->format('Y-m-d H:i:s.v'), (int) round((hrtime(true) - $t0) / 1e6));
            } catch (\Throwable $e) {
                Log::warning('Request log write failed: '.$e->getMessage());
            }
        }

        return $response;
    }

    private function record(Request $request, Response $response, string $startedAt, int $durationMs): void
    {
        if ($request->route() === null && $response->getStatusCode() === 404) {
            self::warn($request, 'not_in_collection');
        }

        MockRequest::query()->create([
            'request_id' => $request->attributes->get(ErrorEnvelope::REQUEST_ID),
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'query' => $request->query(),
            'body' => $this->body($request),
            'api_key_masked' => self::mask($request->header('X-API-KEY')),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'warnings' => $request->attributes->get(self::WARNINGS, []),
            'created_at' => $startedAt,
        ]);

        $keep = max(1, (int) config('unifi_mock.log_keep'));
        $cutoff = MockRequest::query()->orderByDesc('id')->skip($keep)->value('id');
        if ($cutoff !== null) {
            MockRequest::query()->where('id', '<=', $cutoff)->delete();
        }
    }

    private function body(Request $request): ?array
    {
        $raw = $request->getContent();
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['raw' => Str::limit($raw, 2000)];
    }

    /** First 3 and last 3 characters; `none` when absent. Short keys reveal nothing. */
    public static function mask(?string $key): string
    {
        if ($key === null || $key === '') {
            return 'none';
        }

        return strlen($key) <= 8 ? '…' : substr($key, 0, 3).'…'.substr($key, -3);
    }
}

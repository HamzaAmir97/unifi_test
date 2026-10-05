<?php

namespace App\Mock\Errors;

use App\Mock\Unverified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The only place an error body is built (R5, FR-010, G1). Fields are
 * Unverified::ERROR_ENVELOPE_FIELDS, in that order.
 */
final class ErrorEnvelope
{
    /** Request attribute holding the id RecordRequest minted, shared with the log (S7). */
    public const REQUEST_ID = 'unifi_mock.request_id';

    public static function response(Request $request, int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(self::body($request, $status, $code, $message), $status);
    }

    /** @return array<string, int|string> */
    public static function body(Request $request, int $status, string $code, string $message): array
    {
        $requestId = $request->attributes->get(self::REQUEST_ID);
        if (! is_string($requestId)) {
            $requestId = (string) Str::uuid();
            $request->attributes->set(self::REQUEST_ID, $requestId);
        }

        return array_combine(Unverified::ERROR_ENVELOPE_FIELDS, [
            $status,
            self::statusName($status),
            $code,
            $message,
            now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            '/'.ltrim($request->path(), '/'),
            $requestId,
        ]);
    }

    public static function statusName(int $status): string
    {
        $text = Response::$statusTexts[$status] ?? 'Unknown Status';

        return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $text));
    }
}

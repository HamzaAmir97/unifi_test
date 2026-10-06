<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Mock\Errors\MockError;
use App\Models\MockRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /__mock/requests?limit= (newest first, key masked) and DELETE /__mock/requests. */
class RequestLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = $request->query('limit', '50');
        if (! ctype_digit((string) $limit) || (int) $limit < 1 || (int) $limit > 500) {
            throw MockError::invalid('limit must be an integer between 1 and 500.');
        }

        $entries = MockRequest::query()->orderByDesc('id')->limit((int) $limit)->get()->map(fn (MockRequest $r) => [
            'requestId' => $r->request_id,
            'method' => $r->method,
            'path' => $r->path,
            'query' => (object) $r->query,
            'body' => $r->body,
            'apiKey' => $r->api_key_masked,
            'status' => $r->status,
            'durationMs' => $r->duration_ms,
            'warnings' => $r->warnings,
            'at' => $r->created_at?->format('Y-m-d\TH:i:s.v\Z'),
        ]);

        return new JsonResponse($entries);
    }

    public function destroy(): JsonResponse
    {
        return new JsonResponse(['cleared' => MockRequest::query()->delete()]);
    }
}

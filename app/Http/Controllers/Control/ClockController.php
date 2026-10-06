<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Mock\Clock\MockClock;
use App\Mock\Errors\MockError;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /__mock/clock with exactly one of {advanceMinutes}, {now}, {reset: true} (R10). */
class ClockController extends Controller
{
    public function __invoke(Request $request, MockClock $clock): JsonResponse
    {
        $given = array_values(array_intersect(['advanceMinutes', 'now', 'reset'], array_keys($request->all())));
        if (count($given) !== 1) {
            throw MockError::invalid('Send exactly one of advanceMinutes, now, reset.');
        }

        $now = match ($given[0]) {
            'advanceMinutes' => is_int($request->input('advanceMinutes'))
                ? $clock->advanceMinutes($request->input('advanceMinutes'))
                : throw MockError::invalid('advanceMinutes must be an integer.'),
            'now' => $this->set($clock, $request->input('now')),
            'reset' => $request->input('reset') === true ? $clock->reset() : throw MockError::invalid('reset must be true.'),
        };

        return new JsonResponse(['now' => $now->format('Y-m-d\TH:i:s.v\Z'), 'mode' => $clock->state()['mode']]);
    }

    private function set(MockClock $clock, mixed $iso): CarbonImmutable
    {
        try {
            return $clock->set((string) $iso);
        } catch (\InvalidArgumentException $e) {
            throw MockError::invalid($e->getMessage());
        }
    }
}

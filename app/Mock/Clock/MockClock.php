<?php

namespace App\Mock\Clock;

use App\Mock\Settings\MockSettings;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The single time source per request (R10). Stored in `mock_settings.clock` as
 * `{mode: real}`, `{mode: offset, seconds}` or `{mode: frozen, at}`.
 *
 * Real time is read from the system clock directly, never through Carbon::now(), because
 * ApplyMockClock sets Carbon's test-now to the mock time for the rest of the request.
 */
final class MockClock
{
    public function __construct(private readonly MockSettings $settings) {}

    public static function realNow(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)), 'UTC');
    }

    public function now(): CarbonImmutable
    {
        $state = $this->state();

        return match ($state['mode']) {
            'offset' => self::realNow()->addSeconds((int) $state['seconds']),
            'frozen' => CarbonImmutable::parse($state['at'])->utc(),
            default => self::realNow(),
        };
    }

    /** @return array{mode: string, seconds?: int, at?: string} */
    public function state(): array
    {
        $state = $this->settings->get(MockSettings::CLOCK);

        return is_array($state) && isset($state['mode']) ? $state : ['mode' => 'real'];
    }

    public function advanceMinutes(int $minutes): CarbonImmutable
    {
        $state = $this->state();
        if ($state['mode'] === 'frozen') {
            $this->settings->put(MockSettings::CLOCK, ['mode' => 'frozen', 'at' => CarbonImmutable::parse($state['at'])->addMinutes($minutes)->toIso8601ZuluString('millisecond')]);
        } else {
            $seconds = ($state['mode'] === 'offset' ? (int) $state['seconds'] : 0) + $minutes * 60;
            $this->settings->put(MockSettings::CLOCK, ['mode' => 'offset', 'seconds' => $seconds]);
        }

        return $this->now();
    }

    public function set(string $iso): CarbonImmutable
    {
        try {
            $at = CarbonImmutable::parse($iso)->utc();
        } catch (\Throwable) {
            throw new InvalidArgumentException("Not a valid ISO-8601 instant: {$iso}");
        }
        $this->settings->put(MockSettings::CLOCK, ['mode' => 'frozen', 'at' => $at->toIso8601ZuluString('millisecond')]);

        return $this->now();
    }

    public function reset(): CarbonImmutable
    {
        $this->settings->forget(MockSettings::CLOCK);

        return $this->now();
    }
}

<?php

namespace Tests\Unit\Clock;

use App\Mock\Clock\MockClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockClockTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_by_default(): void
    {
        $clock = app(MockClock::class);

        $this->assertSame(['mode' => 'real'], $clock->state());
        $this->assertLessThan(5, abs($clock->now()->diffInSeconds(MockClock::realNow())));
    }

    public function test_advance_accumulates_an_offset(): void
    {
        $clock = app(MockClock::class);
        $clock->advanceMinutes(30);
        $clock->advanceMinutes(31);

        $this->assertSame(['mode' => 'offset', 'seconds' => 61 * 60], $clock->state());
        $this->assertEqualsWithDelta(61 * 60, MockClock::realNow()->diffInSeconds($clock->now()), 5);
    }

    public function test_set_freezes_and_advance_moves_the_frozen_instant(): void
    {
        $clock = app(MockClock::class);
        $clock->set('2026-05-01T08:00:00Z');
        $this->assertSame('2026-05-01T08:00:00.000Z', $clock->now()->format('Y-m-d\TH:i:s.v\Z'));

        $clock->advanceMinutes(90);
        $this->assertSame('2026-05-01T09:30:00.000Z', $clock->now()->format('Y-m-d\TH:i:s.v\Z'));
    }

    public function test_reset_returns_to_real_time(): void
    {
        $clock = app(MockClock::class);
        $clock->set('2000-01-01T00:00:00Z');
        $clock->reset();

        $this->assertSame(['mode' => 'real'], $clock->state());
    }

    public function test_requests_see_the_mock_time(): void
    {
        app(MockClock::class)->set('2030-01-01T00:00:00Z');

        $body = $this->getJson(self::BASE.'/v1/info')->json(); // 401, its envelope timestamp is now()

        $this->assertSame('2030-01-01T00:00:00.000Z', $body['timestamp']);
    }
}

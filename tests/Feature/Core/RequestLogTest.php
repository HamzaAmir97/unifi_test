<?php

namespace Tests\Feature\Core;

use App\Mock\Clock\MockClock;
use App\Models\MockRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_attempt_is_logged_with_the_key_masked(): void
    {
        $this->unifi('GET', '/v1/info');
        $this->getJson(self::BASE.'/v1/info', ['X-API-KEY' => 'wrong-key-123456']);

        $rows = MockRequest::query()->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('tes…789', $rows[0]->api_key_masked);
        $this->assertSame(200, $rows[0]->status);
        $this->assertSame(401, $rows[1]->status);
        foreach ($rows as $row) {
            $this->assertStringNotContainsString(self::KEY, json_encode($row->toArray()));
        }
    }

    public function test_an_unknown_path_carries_the_not_in_collection_warning(): void
    {
        $this->unifi('GET', '/v1/nope');

        $this->assertSame([['type' => 'not_in_collection']], MockRequest::query()->first()->warnings);
    }

    public function test_only_the_newest_rows_are_kept(): void
    {
        config(['unifi_mock.log_keep' => 5]);
        for ($i = 0; $i < 7; $i++) {
            $this->unifi('GET', '/v1/info?n='.$i);
        }

        $this->assertSame(5, MockRequest::query()->count());
        $this->assertSame(['n' => '6'], MockRequest::query()->orderByDesc('id')->first()->query);
    }

    public function test_the_log_keeps_real_time_while_the_mock_clock_moves(): void
    {
        app(MockClock::class)->advanceMinutes(24 * 60);
        $before = MockClock::realNow();

        $this->unifi('GET', '/v1/info');

        $logged = CarbonImmutable::parse(MockRequest::query()->first()->getRawOriginal('created_at'), 'UTC');
        $this->assertLessThan(60, abs($logged->diffInSeconds($before)), 'log time must be real, not mock time');
    }

    public function test_the_envelope_request_id_equals_the_logged_one(): void
    {
        $id = $this->getJson(self::BASE.'/v1/info')->json('requestId');

        $this->assertSame($id, MockRequest::query()->first()->request_id);
    }
}

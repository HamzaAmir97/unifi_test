<?php

namespace Tests\Feature\Core;

use App\Mock\Settings\MockSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R11: RecordRequest → ApplyMockClock → VerifyConsole → RequireApiKey → InjectFault → controller. */
class MiddlewareOrderTest extends TestCase
{
    use RefreshDatabase;

    private function queueFault(int $status = 503): void
    {
        app(MockSettings::class)->put(MockSettings::FAULTS, [
            ['id' => 'f1', 'match' => 'GET /v1/info', 'status' => $status, 'times' => 5, 'delayMs' => 0, 'timeout' => false],
        ]);
    }

    public function test_a_missing_key_is_401_even_with_a_fault_queued(): void
    {
        $this->queueFault();

        $this->assertEnvelope($this->getJson(self::BASE.'/v1/info'), 401);
        $this->assertSame(5, app(MockSettings::class)->get(MockSettings::FAULTS)[0]['times'], 'the fault was not consumed');
    }

    public function test_a_wrong_console_is_refused_before_the_key_is_checked(): void
    {
        $this->assertEnvelope($this->getJson('/v1/connector/consoles/wrong/proxy/network/integration/v1/info'), 404);
    }

    public function test_a_wrong_key_is_401(): void
    {
        $this->assertEnvelope($this->getJson(self::BASE.'/v1/info', ['X-API-KEY' => 'nope']), 401);
    }

    public function test_an_empty_configured_key_refuses_everything(): void
    {
        config(['unifi_mock.api_key' => '']);

        $this->assertEnvelope($this->getJson(self::BASE.'/v1/info', ['X-API-KEY' => '']), 401);
    }

    public function test_with_the_key_the_fault_applies(): void
    {
        $this->queueFault(503);

        $this->assertEnvelope($this->unifi('GET', '/v1/info'), 503);
    }
}

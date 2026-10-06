<?php

namespace Tests\Unit\Faults;

use App\Mock\Errors\MockError;
use App\Mock\Faults\FaultInjector;
use App\Mock\Faults\FaultRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaultInjectorTest extends TestCase
{
    use RefreshDatabase;

    private function injector(): FaultInjector
    {
        return app(FaultInjector::class);
    }

    public function test_match_semantics(): void
    {
        $rule = FaultRule::fromInput(['match' => 'GET */hotspot/vouchers', 'status' => 429], 30000, 'x');

        $this->assertTrue($rule->matches('GET', '/v1/sites/abc/hotspot/vouchers'));
        $this->assertTrue($rule->matches('get', '/v1/sites/abc/hotspot/vouchers'));
        $this->assertFalse($rule->matches('POST', '/v1/sites/abc/hotspot/vouchers'));
        $this->assertFalse($rule->matches('GET', '/v1/sites/abc/hotspot/vouchers/123'));
        $this->assertTrue(FaultRule::fromInput(['match' => '* *', 'status' => 500], 30000, 'y')->matches('DELETE', '/v1/info'));
    }

    public function test_times_decrements_and_the_rule_disappears_at_zero(): void
    {
        $this->injector()->add(['match' => 'GET /v1/info', 'status' => 503, 'times' => 2]);

        $this->assertNotNull($this->injector()->consume('GET', '/v1/info'));
        $this->assertSame(1, $this->injector()->all()[0]->times);
        $this->assertNotNull($this->injector()->consume('GET', '/v1/info'));
        $this->assertSame([], $this->injector()->all());
        $this->assertNull($this->injector()->consume('GET', '/v1/info'));
    }

    public function test_the_control_plane_never_matches(): void
    {
        $this->injector()->add(['match' => '* *', 'status' => 500, 'times' => 5]);

        $this->assertNull($this->injector()->consume('GET', '/__mock/state'));
    }

    public function test_the_path_after_the_connector_base(): void
    {
        $this->assertSame('/v1/sites/x/clients', $this->injector()->relativePath('v1/connector/consoles/mock-console/proxy/network/integration/v1/sites/x/clients'));
    }

    public function test_invalid_rules_are_refused(): void
    {
        foreach ([
            ['match' => 'nonsense', 'status' => 500],
            ['match' => 'GET /x', 'status' => 302],
            ['match' => 'GET /x', 'status' => 500, 'times' => 0],
            ['match' => 'GET /x', 'status' => 500, 'delayMs' => 999999],
            ['match' => 'GET /x'],
        ] as $input) {
            try {
                FaultRule::fromInput($input, 30000, 'z');
                $this->fail('Accepted '.json_encode($input));
            } catch (MockError $e) {
                $this->assertSame(400, $e->status);
            }
        }
    }
}

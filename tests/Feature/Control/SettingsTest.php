<?php

namespace Tests\Feature\Control;

use App\Mock\Scenarios\ScenarioRunner;
use App\Models\MockRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** FR-026 / D2 (strict mode) and FR-012a / G11 (absent vs null). */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScenarioRunner::class)->run('default');
        $this->site = $this->unifi('GET', '/v1/sites')->json('data.0.id');
    }

    private function createRule(string $type): TestResponse
    {
        return $this->unifi('POST', "/v1/sites/{$this->site}/acl-rules", ['name' => 'x', 'enabled' => true, 'type' => $type, 'action' => 'BLOCK']);
    }

    public function test_an_unknown_enum_is_accepted_with_a_warning(): void
    {
        $this->createRule('QUANTUM')->assertCreated();

        $warnings = MockRequest::query()->latest('id')->first()->warnings;
        $this->assertSame([['type' => 'unknown_enum', 'field' => 'type', 'value' => 'QUANTUM']], $warnings);
    }

    public function test_strict_mode_refuses_it(): void
    {
        $this->control('POST', '/settings', ['strict' => true])->assertOk()->assertJsonPath('strict', true);

        $this->assertEnvelope($this->createRule('QUANTUM'), 400);
        $this->createRule('IPV4')->assertCreated();
    }

    public function test_strict_mode_from_the_environment(): void
    {
        config(['unifi_mock.strict' => true]);

        $this->assertEnvelope($this->createRule('QUANTUM'), 400);
    }

    public function test_null_mode_returns_absent_optionals_as_null(): void
    {
        $voucher = $this->unifi('GET', "/v1/sites/{$this->site}/hotspot/vouchers?filter=".urlencode('authorizedGuestCount.eq(0)'))->json('data.0');
        $this->assertArrayNotHasKey('activatedAt', $voucher);

        $this->control('POST', '/settings', ['optionalFields' => 'null'])->assertOk();

        $voucher = $this->unifi('GET', "/v1/sites/{$this->site}/hotspot/vouchers/{$voucher['id']}")->json();
        $this->assertArrayHasKey('activatedAt', $voucher);
        $this->assertNull($voucher['activatedAt']);
    }

    public function test_invalid_values_are_refused_and_reset_restores_defaults(): void
    {
        $this->assertEnvelope($this->control('POST', '/settings', ['optionalFields' => 'maybe']), 400);

        $this->control('POST', '/settings', ['strict' => true, 'optionalFields' => 'null']);
        $this->control('POST', '/reset?seed=default');

        $this->assertSame(['strict' => false, 'optionalFields' => 'omit'], $this->control('POST', '/settings', [])->json());
    }
}

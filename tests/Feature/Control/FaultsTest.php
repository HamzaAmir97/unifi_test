<?php

namespace Tests\Feature\Control;

use App\Mock\Scenarios\ScenarioRunner;
use App\Models\MockRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US4 scenarios 1 and 2. */
class FaultsTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScenarioRunner::class)->run('default');
        $this->site = $this->unifi('GET', '/v1/sites')->json('data.0.id');
    }

    public function test_a_fault_applies_n_times_then_clears(): void
    {
        $this->control('POST', '/faults', ['match' => 'GET */hotspot/vouchers', 'status' => 429, 'times' => 2])->assertCreated();

        $this->assertEnvelope($this->unifi('GET', "/v1/sites/{$this->site}/hotspot/vouchers"), 429);
        $this->assertEnvelope($this->unifi('GET', "/v1/sites/{$this->site}/hotspot/vouchers"), 429);
        $this->unifi('GET', "/v1/sites/{$this->site}/hotspot/vouchers")->assertOk();

        $this->assertSame([], $this->control('GET', '/faults')->json());
        $this->assertSame('fault_injected', MockRequest::query()->where('status', 429)->first()->warnings[0]['type']);
    }

    public function test_a_delay_is_honoured(): void
    {
        $this->control('POST', '/faults', ['match' => 'GET /v1/info', 'delayMs' => 300, 'times' => 1])->assertCreated();

        $start = microtime(true);
        $this->unifi('GET', '/v1/info')->assertOk();

        $this->assertGreaterThanOrEqual(0.3, microtime(true) - $start);
    }

    public function test_a_timeout_answers_504(): void
    {
        $this->control('POST', '/faults', ['match' => 'GET /v1/info', 'timeout' => true]);

        $this->assertEnvelope($this->unifi('GET', '/v1/info'), 504);
    }

    public function test_invalid_rules_and_clear(): void
    {
        $this->assertEnvelope($this->control('POST', '/faults', ['match' => 'GET /v1/info', 'status' => 200]), 400);
        $this->assertEnvelope($this->control('POST', '/faults', ['match' => 'GET /v1/info', 'status' => 500, 'delayMs' => 9999999]), 400);

        $this->control('POST', '/faults', ['match' => 'GET /v1/info', 'status' => 500, 'times' => 3]);
        $this->control('DELETE', '/faults')->assertOk()->assertExactJson(['cleared' => 1]);
        $this->unifi('GET', '/v1/info')->assertOk();
    }

    public function test_faults_never_touch_the_control_plane(): void
    {
        $this->control('POST', '/faults', ['match' => '* *', 'status' => 500, 'times' => 5]);

        $this->control('GET', '/state')->assertOk();
    }
}

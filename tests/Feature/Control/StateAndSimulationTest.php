<?php

namespace Tests\Feature\Control;

use App\Mock\Scenarios\ScenarioRunner;
use App\Models\MockRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US4 scenarios 3 and 4, plus client and device simulation. */
class StateAndSimulationTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScenarioRunner::class)->run('default');
        $this->site = $this->unifi('GET', '/v1/sites')->json('data.0.id');
    }

    public function test_reset_to_each_scenario_and_back_to_real_time(): void
    {
        $this->control('POST', '/clock', ['advanceMinutes' => 1000]);

        foreach (['empty' => 0, 'multi-site' => 2, 'large' => 1, 'default' => 1] as $seed => $sites) {
            $counts = $this->control('POST', "/reset?seed={$seed}")->assertOk()->json('counts');
            $this->assertSame($sites, $counts['site'] ?? 0, $seed);
        }

        $this->assertSame('real', $this->control('GET', '/state')->json('clock.mode'));
        $this->assertEnvelope($this->control('POST', '/reset?seed=nope'), 400);
    }

    public function test_state_shows_everything(): void
    {
        $state = $this->control('GET', '/state')->assertOk()->json();

        $this->assertSame(['clock', 'strict', 'optionalFields', 'faults', 'resources'], array_keys($state));
        $this->assertCount(5, $state['resources']['voucher']);
        $this->assertFalse($state['strict']);
        $this->assertSame('omit', $state['optionalFields']);
    }

    public function test_connect_and_disconnect_a_client(): void
    {
        $client = $this->control('POST', '/clients/connect', ['siteId' => $this->site, 'name' => 'Walk-in', 'type' => 'WIRELESS'])->assertCreated()->json();
        $this->assertContains($client['id'], array_column($this->unifi('GET', "/v1/sites/{$this->site}/clients")->json('data'), 'id'));

        $this->control('POST', '/clients/disconnect', ['siteId' => $this->site, 'clientId' => $client['id']])->assertOk();
        $this->assertNotContains($client['id'], array_column($this->unifi('GET', "/v1/sites/{$this->site}/clients")->json('data'), 'id'));

        $this->assertEnvelope($this->control('POST', '/clients/disconnect', ['siteId' => $this->site, 'clientId' => $client['id']]), 404);
        $this->assertEnvelope($this->control('POST', '/clients/connect', ['siteId' => '00000000-0000-4000-8000-000000000000', 'name' => 'x', 'type' => 'WIRED']), 404);
    }

    public function test_a_new_pending_device_can_be_adopted(): void
    {
        $this->control('POST', '/devices/pending', ['macAddress' => '02:A7:00:00:10:01', 'model' => 'U7-Lite'])->assertCreated();
        $this->assertEnvelope($this->control('POST', '/devices/pending', ['macAddress' => '02:a7:00:00:10:01', 'model' => 'U7-Lite']), 409);

        $this->unifi('POST', "/v1/sites/{$this->site}/devices", ['macAddress' => '02:a7:00:00:10:01', 'ignoreDeviceLimit' => false])->assertOk();
    }

    public function test_the_request_log_masks_the_key_and_lists_newest_first(): void
    {
        $this->unifi('GET', '/v1/info');
        $this->unifi('GET', '/v1/nope');

        $entries = $this->control('GET', '/requests?limit=10')->assertOk()->json();

        $this->assertSame('/v1/connector/consoles/mock-console/proxy/network/integration/v1/nope', $entries[0]['path']);
        $this->assertSame('not_in_collection', $entries[0]['warnings'][0]['type']);
        $this->assertSame('tes…789', $entries[0]['apiKey']);
        $this->assertStringNotContainsString(self::KEY, $this->control('GET', '/requests')->getContent());

        $this->control('DELETE', '/requests')->assertOk();
        $this->assertSame(1, MockRequest::query()->count(), 'only the DELETE itself is logged after clearing');
    }
}

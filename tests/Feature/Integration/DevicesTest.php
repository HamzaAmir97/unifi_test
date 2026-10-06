<?php

namespace Tests\Feature\Integration;

use App\Mock\Clock\MockClock;
use App\Mock\Scenarios\ScenarioRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicesTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScenarioRunner::class)->run('default');
        $this->site = $this->unifi('GET', '/v1/sites')->json('data.0.id');
    }

    private function device(string $name): array
    {
        return $this->unifi('GET', "/v1/sites/{$this->site}/devices?filter=".urlencode("name.eq('{$name}')"))->json('data.0');
    }

    public function test_pending_devices_are_global_and_listed(): void
    {
        $pending = $this->unifi('GET', '/v1/pending-devices')->assertOk()->json('data.0');

        $this->assertSame('PENDING_ADOPTION', $pending['state']);
        $this->assertSame([$this->site], $pending['adoptionTargetSiteIds']);
        $this->assertSame(['accessPoint'], $pending['features']);
    }

    public function test_adopt_answers_200_and_moves_the_device(): void
    {
        $mac = $this->unifi('GET', '/v1/pending-devices')->json('data.0.macAddress');
        $before = $this->unifi('GET', "/v1/sites/{$this->site}/devices")->json('totalCount');

        $adopted = $this->unifi('POST', "/v1/sites/{$this->site}/devices", ['macAddress' => strtoupper($mac), 'ignoreDeviceLimit' => false]);

        $adopted->assertStatus(200);
        $this->assertSame($mac, $adopted->json('macAddress'));
        $this->assertSame(0, $this->unifi('GET', '/v1/pending-devices')->json('totalCount'));
        $this->assertSame($before + 1, $this->unifi('GET', "/v1/sites/{$this->site}/devices")->json('totalCount'));
    }

    public function test_adopt_needs_both_fields_and_a_pending_mac(): void
    {
        $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$this->site}/devices", ['macAddress' => '02:00:00:00:00:99']), 400);
        $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$this->site}/devices", ['macAddress' => '02:00:00:00:00:99', 'ignoreDeviceLimit' => false]), 404);
    }

    public function test_list_and_details_shapes(): void
    {
        $summary = $this->device('Lobby AP');
        $this->assertSame(['accessPoint'], $summary['features']);
        $this->assertSame(['ports', 'radios'], $summary['interfaces']);

        $details = $this->unifi('GET', "/v1/sites/{$this->site}/devices/{$summary['id']}")->assertOk()->json();
        $this->assertArrayHasKey('accessPoint', $details['features']);
        $this->assertSame(1, $details['interfaces']['ports'][0]['idx']);
        $this->assertCount(3, $details['interfaces']['radios']);
        $this->assertArrayHasKey('deviceId', $details['uplink']);
        $this->assertStringContainsString('"accessPoint":{}', $this->unifi('GET', "/v1/sites/{$this->site}/devices/{$summary['id']}")->getContent(), 'an empty feature is an object');
    }

    public function test_features_and_interfaces_are_filterable_sets(): void
    {
        $switches = $this->unifi('GET', "/v1/sites/{$this->site}/devices?filter=".urlencode("features.contains('switching')"))->json('totalCount');

        $this->assertSame(4, $switches);
    }

    public function test_actions_answer_200_with_an_empty_text_body(): void
    {
        $id = $this->device('Lobby AP')['id'];

        foreach ([["/v1/sites/{$this->site}/devices/{$id}/actions", 'RESTART'], ["/v1/sites/{$this->site}/devices/{$id}/interfaces/ports/1/actions", 'POWER_CYCLE']] as [$path, $action]) {
            $response = $this->unifi('POST', $path, ['action' => $action]);
            $response->assertOk();
            $this->assertSame('', $response->getContent());
            $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        }
    }

    public function test_restart_shows_updating_for_a_while(): void
    {
        $id = $this->device('Lobby AP')['id'];
        $this->unifi('POST', "/v1/sites/{$this->site}/devices/{$id}/actions", ['action' => 'RESTART']);

        $this->assertSame('UPDATING', $this->unifi('GET', "/v1/sites/{$this->site}/devices/{$id}")->json('state'));
        app(MockClock::class)->advanceMinutes(2);
        $this->assertSame('ONLINE', $this->unifi('GET', "/v1/sites/{$this->site}/devices/{$id}")->json('state'));
    }

    public function test_an_unknown_port_is_404(): void
    {
        $id = $this->device('Lobby AP')['id'];

        $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$this->site}/devices/{$id}/interfaces/ports/99/actions", ['action' => 'POWER_CYCLE']), 404);
    }

    public function test_statistics_percentages_are_within_bounds(): void
    {
        $id = $this->device('Lobby AP')['id'];
        $stats = $this->unifi('GET', "/v1/sites/{$this->site}/devices/{$id}/statistics/latest")->assertOk()->json();

        foreach (['cpuUtilizationPct', 'memoryUtilizationPct'] as $key) {
            $this->assertGreaterThanOrEqual(0, $stats[$key]);
            $this->assertLessThanOrEqual(100, $stats[$key]);
        }
        $this->assertCount(3, $stats['interfaces']['radios']);
    }

    public function test_remove(): void
    {
        $id = $this->device('Lobby AP')['id'];

        $this->unifi('DELETE', "/v1/sites/{$this->site}/devices/{$id}")->assertOk();
        $this->assertEnvelope($this->unifi('GET', "/v1/sites/{$this->site}/devices/{$id}"), 404);
    }
}

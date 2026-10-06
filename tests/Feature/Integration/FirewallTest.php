<?php

namespace Tests\Feature\Integration;

use App\Mock\Scenarios\ScenarioRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirewallTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScenarioRunner::class)->run('default');
        $this->site = $this->unifi('GET', '/v1/sites')->json('data.0.id');
    }

    private function zone(string $name): string
    {
        return $this->unifi('GET', "/v1/sites/{$this->site}/firewall/zones?filter=".urlencode("name.eq('{$name}')"))->json('data.0.id');
    }

    private function policy(string $name): array
    {
        return $this->unifi('GET', "/v1/sites/{$this->site}/firewall/policies?filter=".urlencode("name.eq('{$name}')"))->json('data.0');
    }

    private function orderingPath(): string
    {
        return "/v1/sites/{$this->site}/firewall/policies/ordering?sourceFirewallZoneId={$this->zone('Hotspot')}&destinationFirewallZoneId={$this->zone('Internal')}";
    }

    public function test_system_zones_cannot_be_edited_or_deleted(): void
    {
        $internal = $this->zone('Internal');

        $this->assertEnvelope($this->unifi('PUT', "/v1/sites/{$this->site}/firewall/zones/{$internal}", ['name' => 'X', 'networkIds' => []]), 400);
        $this->assertEnvelope($this->unifi('DELETE', "/v1/sites/{$this->site}/firewall/zones/{$internal}"), 400);
        $this->assertSame(1, $this->unifi('GET', "/v1/sites/{$this->site}/firewall/zones?filter=".urlencode('metadata.configurable.eq(true)'))->json('totalCount'));
    }

    public function test_custom_zone_crud_and_network_check(): void
    {
        $created = $this->unifi('POST', "/v1/sites/{$this->site}/firewall/zones", ['name' => 'Printers', 'networkIds' => []])->assertCreated()->json();
        $this->unifi('PUT', "/v1/sites/{$this->site}/firewall/zones/{$created['id']}", ['name' => 'Printers 2', 'networkIds' => []])->assertOk()->assertJsonPath('name', 'Printers 2');
        $this->unifi('DELETE', "/v1/sites/{$this->site}/firewall/zones/{$created['id']}")->assertOk();

        $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$this->site}/firewall/zones", ['name' => 'Bad', 'networkIds' => ['00000000-0000-4000-8000-000000000000']]), 400);
    }

    public function test_create_assigns_an_index_and_checks_zones(): void
    {
        $body = ['name' => 'New', 'enabled' => true, 'loggingEnabled' => false, 'action' => ['type' => 'ALLOW'],
            'source' => ['zoneId' => $this->zone('Internal')], 'destination' => ['zoneId' => $this->zone('External')], 'ipProtocolScope' => ['ipVersion' => 'IPV4']];

        $created = $this->unifi('POST', "/v1/sites/{$this->site}/firewall/policies", $body)->assertCreated()->json();
        $this->assertIsInt($created['index']);

        $body['source']['zoneId'] = '00000000-0000-4000-8000-000000000000';
        $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$this->site}/firewall/policies", $body), 400);
    }

    public function test_patch_changes_only_the_fields_sent(): void
    {
        $before = $this->policy('Guests cannot reach members');

        $after = $this->unifi('PATCH', "/v1/sites/{$this->site}/firewall/policies/{$before['id']}", ['loggingEnabled' => true])->assertOk()->json();

        $this->assertTrue($after['loggingEnabled']);
        $before['loggingEnabled'] = true;
        $this->assertSame($before, $after);
    }

    public function test_ordering_is_never_matched_as_a_policy_id(): void
    {
        $ordering = $this->unifi('GET', $this->orderingPath())->assertOk()->json('orderedFirewallPolicyIds');

        $this->assertSame(['afterSystemDefined', 'beforeSystemDefined'], array_keys($ordering));
        $this->assertCount(2, $ordering['beforeSystemDefined']);
        $this->assertCount(1, $ordering['afterSystemDefined']);
    }

    public function test_reorder_rewrites_the_order_and_the_indexes(): void
    {
        $o = $this->unifi('GET', $this->orderingPath())->json('orderedFirewallPolicyIds');
        $new = ['beforeSystemDefined' => array_reverse($o['beforeSystemDefined']), 'afterSystemDefined' => $o['afterSystemDefined']];

        $this->unifi('PUT', $this->orderingPath(), ['orderedFirewallPolicyIds' => $new])->assertOk()->assertJsonPath('orderedFirewallPolicyIds.beforeSystemDefined', $new['beforeSystemDefined']);

        $first = $this->unifi('GET', "/v1/sites/{$this->site}/firewall/policies/{$new['beforeSystemDefined'][0]}")->json('index');
        $second = $this->unifi('GET', "/v1/sites/{$this->site}/firewall/policies/{$new['beforeSystemDefined'][1]}")->json('index');
        $this->assertLessThan($second, $first);
    }

    public function test_reorder_with_a_different_set_is_refused(): void
    {
        $o = $this->unifi('GET', $this->orderingPath())->json('orderedFirewallPolicyIds');

        $this->assertEnvelope($this->unifi('PUT', $this->orderingPath(), ['orderedFirewallPolicyIds' => ['beforeSystemDefined' => $o['beforeSystemDefined'], 'afterSystemDefined' => []]]), 400);
    }

    public function test_ordering_needs_both_zone_ids(): void
    {
        $this->assertEnvelope($this->unifi('GET', "/v1/sites/{$this->site}/firewall/policies/ordering?sourceFirewallZoneId={$this->zone('Hotspot')}"), 400);
    }

    public function test_a_zone_used_by_a_policy_cannot_be_deleted(): void
    {
        $lab = $this->zone('Lab');
        $this->unifi('POST', "/v1/sites/{$this->site}/firewall/policies", ['name' => 'Lab out', 'enabled' => true, 'loggingEnabled' => false, 'action' => ['type' => 'ALLOW'],
            'source' => ['zoneId' => $lab], 'destination' => ['zoneId' => $this->zone('External')], 'ipProtocolScope' => ['ipVersion' => 'IPV4']])->assertCreated();

        $this->assertEnvelope($this->unifi('DELETE', "/v1/sites/{$this->site}/firewall/zones/{$lab}"), 409);
    }
}

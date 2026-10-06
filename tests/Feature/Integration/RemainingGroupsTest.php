<?php

namespace Tests\Feature\Integration;

use App\Mock\Manifest\Manifest;
use App\Mock\Scenarios\ScenarioRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Switching, DNS policies, traffic matching lists and Supporting Resources. */
class RemainingGroupsTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScenarioRunner::class)->run('default');
        $this->site = $this->unifi('GET', '/v1/sites')->json('data.0.id');
    }

    public function test_switching_is_derived_from_the_devices(): void
    {
        $lags = $this->unifi('GET', "/v1/sites/{$this->site}/switching/lags")->assertOk()->json();
        $this->assertSame(2, $lags['totalCount']);
        $lag = $this->unifi('GET', "/v1/sites/{$this->site}/switching/lags/{$lags['data'][0]['id']}")->assertOk()->json();
        $this->assertSame(['id', 'members', 'metadata', 'type'], array_keys($lag));
        $this->assertCount(2, $lag['members']);

        $domain = $this->unifi('GET', "/v1/sites/{$this->site}/switching/mc-lag-domains")->json('data.0');
        $this->assertSame(['TOP', 'BOTTOM'], array_column($domain['peers'], 'role'));
        $this->assertCount(1, $domain['lags']);

        $stack = $this->unifi('GET', "/v1/sites/{$this->site}/switching/switch-stacks")->json('data.0');
        $this->assertSame('Floor 1 stack', $stack['name']);
        $this->assertCount(2, $stack['members']);

        $coreA = $this->unifi('GET', "/v1/sites/{$this->site}/devices?filter=".urlencode("name.eq('Core Switch A')"))->json('data.0.id');
        $this->assertSame(1, $this->unifi('GET', "/v1/sites/{$this->site}/switching/lags?filter=".urlencode("members.deviceId.contains('{$coreA}')"))->json('totalCount'));

        $this->unifi('DELETE', "/v1/sites/{$this->site}/devices/{$coreA}")->assertOk();
        $this->assertCount(1, $this->unifi('GET', "/v1/sites/{$this->site}/switching/mc-lag-domains")->json('data.0.peers'), 'removing a device changes the derived topology');
        $this->assertEnvelope($this->unifi('GET', "/v1/sites/{$this->site}/switching/lags/00000000-0000-4000-8000-000000000000"), 404);
    }

    public function test_dns_policy_fields_follow_the_type(): void
    {
        $created = $this->unifi('POST', "/v1/sites/{$this->site}/dns/policies", [
            'enabled' => true, 'type' => 'A_RECORD', 'domain' => 'x.apex.example', 'ipv4Address' => '192.0.2.9', 'text' => 'ignored for A records',
        ])->assertCreated()->json();

        $this->assertSame('192.0.2.9', $created['ipv4Address']);
        $this->assertArrayNotHasKey('text', $created);
        $this->unifi('PUT', "/v1/sites/{$this->site}/dns/policies/{$created['id']}", ['enabled' => false, 'type' => 'TXT_RECORD', 'domain' => 'x.apex.example', 'text' => 'hello'])
            ->assertOk()->assertJsonPath('text', 'hello')->assertJsonMissingPath('ipv4Address');
        $this->unifi('DELETE', "/v1/sites/{$this->site}/dns/policies/{$created['id']}")->assertOk();
    }

    public function test_all_17_dns_filter_properties_are_accepted(): void
    {
        $samples = ['STRING' => "'x'", 'UUID' => "'00000000-0000-4000-8000-000000000000'", 'BOOLEAN' => 'true', 'INTEGER' => '1'];
        $table = app(Manifest::class)->filterTable('list-dns-policies');
        $this->assertCount(17, $table);
        foreach ($table as $name => $p) {
            $this->unifi('GET', "/v1/sites/{$this->site}/dns/policies?filter=".urlencode("{$name}.{$p['functions'][0]}(".(in_array($p['functions'][0], ['isNull', 'isNotNull', 'isEmpty'], true) ? '' : $samples[$p['type']]).')'))->assertOk();
        }
        $this->assertSame(1, $this->unifi('GET', "/v1/sites/{$this->site}/dns/policies?filter=".urlencode('port.eq(5060)'))->json('totalCount'));
    }

    public function test_traffic_matching_lists(): void
    {
        $created = $this->unifi('POST', "/v1/sites/{$this->site}/traffic-matching-lists", ['name' => 'VoIP', 'type' => 'PORTS'])->assertCreated()->json();
        $this->assertSame(['id', 'name', 'type'], array_keys($created));
        $this->unifi('PUT', "/v1/sites/{$this->site}/traffic-matching-lists/{$created['id']}", ['name' => 'VoIP 2', 'type' => 'PORTS'])->assertOk()->assertJsonPath('name', 'VoIP 2');
        $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$this->site}/traffic-matching-lists", ['name' => 'no type']), 400);
    }

    public function test_reference_lists(): void
    {
        $sa = $this->unifi('GET', '/v1/countries?filter='.urlencode("code.eq('SA')"))->assertOk()->json('data.0');
        $this->assertSame(['code' => 'SA', 'name' => 'Saudi Arabia'], $sa);

        $app = $this->unifi('GET', '/v1/dpi/applications')->json('data.0');
        $this->assertIsInt($app['id']);
        $this->assertSame(1, $this->unifi('GET', '/v1/dpi/categories?filter='.urlencode('id.eq(4)'))->json('totalCount'));

        foreach (['radius/profiles' => 2, 'vpn/servers' => 2, 'vpn/site-to-site-tunnels' => 1, 'wans' => 2] as $path => $count) {
            $this->assertSame($count, $this->unifi('GET', "/v1/sites/{$this->site}/{$path}")->assertOk()->json('totalCount'), $path);
        }
    }

    public function test_device_tags_answer_without_a_filter_and_honour_one_if_sent(): void
    {
        $this->assertSame(2, $this->unifi('GET', "/v1/sites/{$this->site}/device-tags")->assertOk()->json('totalCount'));
        $this->assertSame(1, $this->unifi('GET', "/v1/sites/{$this->site}/device-tags?filter=".urlencode("name.eq('Core')"))->json('totalCount'));
    }

    public function test_wans_declare_no_filter_properties(): void
    {
        $this->assertEnvelope($this->unifi('GET', "/v1/sites/{$this->site}/wans?filter=".urlencode("name.eq('x')")), 400);
    }
}

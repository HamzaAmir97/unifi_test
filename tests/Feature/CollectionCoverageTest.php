<?php

namespace Tests\Feature;

use App\Enums\ResourceType;
use App\Mock\Manifest\Manifest;
use App\Mock\Scenarios\ScenarioRunner;
use App\Mock\Store\ResourceStore;
use App\Models\MockResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SC-001: every request in the collection answers with its documented status code and
 * content type, with valid values from the default scenario. Each request runs on a fresh
 * reset so a delete never starves a later read.
 */
class CollectionCoverageTest extends TestCase
{
    use RefreshDatabase;

    public static function entries(): array
    {
        $manifest = new Manifest(dirname(__DIR__, 2).'/resources/manifest.json');
        $out = [];
        foreach ($manifest->entries() as $entry) {
            $out[$entry['id']] = [$entry['id']];
        }

        return $out;
    }

    #[DataProvider('entries')]
    public function test_the_request_answers_as_documented(string $id): void
    {
        app(ScenarioRunner::class)->run('default');
        $entry = app(Manifest::class)->entry($id);
        $ids = $this->ids();

        [$path, $query, $body] = $this->request($entry, $ids);
        $response = $this->unifi($entry['method'], $path.($query ? '?'.http_build_query($query) : ''), $body);

        $this->assertNotSame(501, $response->getStatusCode(), "{$id} is not mocked");
        $this->assertSame($entry['status'], $response->getStatusCode(), "{$id}: ".$response->getContent());
        $this->assertStringStartsWith($entry['contentType'], (string) $response->headers->get('Content-Type'), $id);
        if ($entry['contentType'] === 'text/plain') {
            $this->assertSame('', $response->getContent(), $id);
        }
    }

    private function ids(): array
    {
        $store = app(ResourceStore::class);
        $site = $store->all(ResourceType::Site)->first()->uuid;
        $named = fn (ResourceType $t, string $name) => $store->all($t, $site)->first(fn ($r) => ($r->payload['name'] ?? null) === $name)->uuid;
        $first = fn (ResourceType $t) => $store->all($t, $site)->first()->uuid;
        $get = fn (string $p) => $this->unifi('GET', "/v1/sites/{$site}/{$p}")->json('data.0.id');

        return [
            'siteId' => $site,
            'deviceId' => $named(ResourceType::Device, 'Lobby AP'),
            'portIdx' => 1,
            'clientId' => $first(ResourceType::Client),
            'networkId' => $named(ResourceType::Network, 'Members'),
            'wifiBroadcastId' => $named(ResourceType::WifiBroadcast, 'APEX Members'),
            'voucherId' => $first(ResourceType::Voucher),
            'firewallPolicyId' => $named(ResourceType::FirewallPolicy, 'Guests cannot reach members'),
            'firewallZoneId' => $named(ResourceType::FirewallZone, 'Lab'),
            'hotspotZone' => $named(ResourceType::FirewallZone, 'Hotspot'),
            'internalZone' => $named(ResourceType::FirewallZone, 'Internal'),
            'aclRuleId' => $first(ResourceType::AclRule),
            'lagId' => $get('switching/lags'),
            'mcLagDomainId' => $get('switching/mc-lag-domains'),
            'switchStackId' => $get('switching/switch-stacks'),
            'dnsPolicyId' => $first(ResourceType::DnsPolicy),
            'trafficMatchingListId' => $first(ResourceType::TrafficMatchingList),
            'pendingMac' => $store->all(ResourceType::PendingDevice)->first()->payload['macAddress'],
        ];
    }

    /** @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>} */
    private function request(array $entry, array $ids): array
    {
        $path = $entry['path'];
        if ($entry['id'] === 'delete-network') {
            // A network nothing references, so the documented force=false succeeds.
            $ids['networkId'] = app(ResourceStore::class)->create(ResourceType::Network, $ids['siteId'], [
                'name' => 'Spare', 'enabled' => true, 'management' => 'GATEWAY', 'vlanId' => 99, 'default' => false, 'metadata' => ['origin' => 'USER_DEFINED'],
            ])->uuid;
        }
        foreach ($entry['pathParams'] as $param) {
            if ($param !== 'consoleId') {
                $path = str_replace('{'.$param.'}', (string) $ids[$param], $path);
            }
        }

        $query = [];
        foreach ($entry['query'] as $q) {
            if (! $q['disabled'] && $q['key'] !== 'filter') {
                $query[$q['key']] = $q['default'];
            }
        }
        if (isset($query['sourceFirewallZoneId'])) {
            $query = ['sourceFirewallZoneId' => $ids['hotspotZone'], 'destinationFirewallZoneId' => $ids['internalZone']];
        }
        if ($entry['id'] === 'delete-vouchers') {
            $query['filter'] = 'expired.eq(true)';
        }

        return [$path, $query, $this->body($entry['id'], $ids)];
    }

    private function body(string $id, array $ids): array
    {
        $broadcast = [
            'name' => 'Coverage', 'type' => 'STANDARD', 'enabled' => true, 'channel2gLockedTo6' => false, 'clientIsolationEnabled' => false,
            'dtimPeriod2gLockedTo3' => false, 'hideName' => false, 'multicastToUnicastConversionEnabled' => true, 'uapsdEnabled' => true,
            'securityConfiguration' => ['type' => 'WPA2_PERSONAL'],
        ];
        $policy = [
            'name' => 'Coverage', 'enabled' => true, 'loggingEnabled' => false, 'action' => ['type' => 'BLOCK'],
            'source' => ['zoneId' => $ids['hotspotZone']], 'destination' => ['zoneId' => $ids['internalZone']], 'ipProtocolScope' => ['ipVersion' => 'IPV4'],
        ];

        return match ($id) {
            'adopt-devices' => ['macAddress' => $ids['pendingMac'], 'ignoreDeviceLimit' => false],
            'execute-adopted-device-action' => ['action' => 'RESTART'],
            'execute-port-action' => ['action' => 'POWER_CYCLE'],
            'execute-client-action' => ['action' => 'AUTHORIZE_GUEST_ACCESS'],
            'create-network', 'update-network' => ['name' => 'Coverage', 'enabled' => true, 'management' => 'GATEWAY', 'vlanId' => 77],
            'create-wifi-broadcast', 'update-wifi-broadcast' => $broadcast,
            'generate-vouchers' => ['name' => 'Coverage', 'timeLimitMinutes' => 60],
            'create-firewall-policy', 'update-firewall-policy' => $policy,
            'patch-firewall-policy' => ['loggingEnabled' => true],
            'reorder-user-defined-firewall-policies' => $this->unifi('GET', "/v1/sites/{$ids['siteId']}/firewall/policies/ordering?sourceFirewallZoneId={$ids['hotspotZone']}&destinationFirewallZoneId={$ids['internalZone']}")->json(),
            'create-custom-firewall-zone', 'update-firewall-zone' => ['name' => 'Coverage', 'networkIds' => []],
            'create-acl-rule', 'update-acl-rule' => ['name' => 'Coverage', 'enabled' => true, 'type' => 'IPV4', 'action' => 'BLOCK'],
            'reorder-user-defined-acl-rules' => ['orderedAclRuleIds' => array_reverse(MockResource::query()->where('type', 'acl_rule')->orderBy('position')->pluck('uuid')->all())],
            'create-dns-policy', 'update-dns-policy' => ['enabled' => true, 'type' => 'A_RECORD', 'domain' => 'coverage.apex.example', 'ipv4Address' => '192.0.2.50'],
            'create-traffic-matching-list', 'update-traffic-matching-list' => ['name' => 'Coverage', 'type' => 'PORTS'],
            default => [],
        };
    }
}

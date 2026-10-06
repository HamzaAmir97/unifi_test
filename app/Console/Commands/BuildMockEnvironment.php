<?php

namespace App\Console\Commands;

use App\Enums\ResourceType;
use App\Mock\Scenarios\ScenarioRunner;
use App\Mock\Store\ResourceStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes postman/unifi-mock.postman_environment.json. Besides the four values every
 * environment has (baseUrl, consoleId, apiKey, siteId), the mock environment carries
 * MOCK-ONLY helpers: controlBase, resetBeforeEach and the default scenario's ids, which
 * are deterministic. The scenario is built inside a rolled-back transaction, so running
 * this command never changes the mock's state.
 */
class BuildMockEnvironment extends Command
{
    public const TARGET = 'postman/unifi-mock.postman_environment.json';

    /** Keys only the mock environment has; the production template has none of them. */
    public const MOCK_ONLY = ['controlBase', 'resetBeforeEach', 'requiredFilter', 'deviceId', 'portIdx', 'clientId', 'networkId', 'networkIds',
        'wifiBroadcastId', 'voucherId', 'firewallPolicyId', 'firewallZoneId', 'zoneId', 'sourceFirewallZoneId', 'destinationFirewallZoneId',
        'aclRuleId', 'lagId', 'mcLagDomainId', 'switchStackId', 'dnsPolicyId', 'trafficMatchingListId', 'macAddress'];

    protected $signature = 'mock:build-mock-environment';

    protected $description = 'Generate the Postman mock environment with the default scenario ids';

    public function handle(ScenarioRunner $runner, ResourceStore $store): int
    {
        DB::beginTransaction();
        try {
            $runner->run('default');
            $ids = $this->ids($store);
        } finally {
            DB::rollBack();
        }

        $values = [
            'baseUrl' => 'http://127.0.0.1:8091/v1/connector/consoles/{{consoleId}}/proxy/network/integration',
            'consoleId' => 'mock-console',
            'apiKey' => 'local-dev-key',
            'siteId' => $ids['siteId'],
            'controlBase' => 'http://127.0.0.1:8091/__mock',
            'resetBeforeEach' => 'true',
            'requiredFilter' => 'expired.eq(true)',
        ] + $ids;

        $environment = [
            'id' => '8d9a4b7e-5f0c-5c1d-9e2a-3b4c5d6e7f80',
            'name' => 'unifi-mock',
            'values' => array_map(fn ($k, $v) => ['key' => $k, 'value' => (string) $v, 'type' => $k === 'apiKey' ? 'secret' : 'default', 'enabled' => true], array_keys($values), $values),
            '_postman_variable_scope' => 'environment',
        ];

        file_put_contents(base_path(self::TARGET), json_encode($environment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $this->info('Written '.self::TARGET.'.');

        return self::SUCCESS;
    }

    private function ids(ResourceStore $store): array
    {
        $site = $store->all(ResourceType::Site)->first()->uuid;
        $named = fn (ResourceType $t, string $name) => $store->all($t, $site)->first(fn ($r) => ($r->payload['name'] ?? null) === $name)?->uuid;
        $first = fn (ResourceType $t) => $store->all($t, $site)->first()?->uuid;
        $lab = $named(ResourceType::Network, 'Lab');
        $devices = $store->all(ResourceType::Device, $site);
        $lagId = array_key_first($devices->first(fn ($d) => isset($d->payload['switchingRoles']['lags']))->payload['switchingRoles']['lags']);
        $mcLag = $devices->first(fn ($d) => isset($d->payload['switchingRoles']['mcLag']))->payload['switchingRoles']['mcLag']['domainId'];
        $stack = $devices->first(fn ($d) => isset($d->payload['switchingRoles']['stack']))->payload['switchingRoles']['stack']['id'];

        return [
            'siteId' => $site,
            'deviceId' => $named(ResourceType::Device, 'Lobby AP'),
            'portIdx' => 1,
            'clientId' => $first(ResourceType::Client),
            'networkId' => $lab,
            'networkIds' => $lab,
            'wifiBroadcastId' => $named(ResourceType::WifiBroadcast, 'APEX Members'),
            'voucherId' => $first(ResourceType::Voucher),
            'firewallPolicyId' => $named(ResourceType::FirewallPolicy, 'Guests cannot reach members'),
            'firewallZoneId' => $named(ResourceType::FirewallZone, 'Lab'),
            'zoneId' => $named(ResourceType::FirewallZone, 'Internal'),
            'sourceFirewallZoneId' => $named(ResourceType::FirewallZone, 'Hotspot'),
            'destinationFirewallZoneId' => $named(ResourceType::FirewallZone, 'Internal'),
            'aclRuleId' => $first(ResourceType::AclRule),
            'lagId' => $lagId,
            'mcLagDomainId' => $mcLag,
            'switchStackId' => $stack,
            'dnsPolicyId' => $first(ResourceType::DnsPolicy),
            'trafficMatchingListId' => $first(ResourceType::TrafficMatchingList),
            'macAddress' => $store->all(ResourceType::PendingDevice)->first()->payload['macAddress'],
        ];
    }
}

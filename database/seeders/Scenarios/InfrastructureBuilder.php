<?php

namespace Database\Seeders\Scenarios;

use App\Enums\ResourceType;
use App\Mock\Scenarios\ScenarioKit;
use App\Models\MockResource;

/**
 * Devices (with a switch stack, an MC-LAG pair and LAGs), firewall zones and policies, ACL
 * rules, DNS policies, traffic matching lists and the site-scoped reference data.
 * Invented and hand-authored (D5, FR-011).
 */
final class InfrastructureBuilder
{
    public function __construct(private readonly ScenarioKit $kit) {}

    /** @param array{lan: MockResource, members: MockResource, guest: MockResource} $nets */
    public function build(MockResource $site, array $nets, int $block = 0): void
    {
        $s = $site->uuid;
        $lab = $this->kit->add(ResourceType::Network, $s, 'lab', [
            'name' => 'Lab', 'enabled' => false, 'management' => 'GATEWAY', 'vlanId' => 50, 'default' => false,
            'metadata' => ['origin' => 'USER_DEFINED'],
        ]);

        $devices = $this->devices($s, $block);
        $this->firewall($s, $nets, $lab);
        $this->acl($s, $nets);
        $this->dns($s);
        $this->references($s, $devices);
    }

    /** @return array<string, MockResource> */
    private function devices(string $s, int $block): array
    {
        $now = $this->kit->now;
        $adopted = $this->kit->iso($now->subDays(120));
        $lagCore = $this->kit->id("lag:{$s}:core-uplink");
        $lagStack = $this->kit->id("lag:{$s}:stack-uplink");
        $stack = ['id' => $this->kit->id("stack:{$s}:floor1"), 'name' => 'Floor 1 stack'];
        $mcLag = $this->kit->id("mclag:{$s}:core");

        $n = 0;
        $make = function (string $key, string $name, string $model, array $features, array $interfaces, array $extra = []) use ($s, $adopted, $block, &$n): MockResource {
            $n++;

            return $this->kit->add(ResourceType::Device, $s, $key, [
                'name' => $name, 'model' => $model, 'macAddress' => $this->kit->mac(), 'ipAddress' => ScenarioKit::ip(2 + $n, $block),
                'state' => 'ONLINE', 'supported' => true, 'firmwareVersion' => '7.1.68', 'firmwareUpdatable' => $n % 2 === 0,
                'configurationId' => $this->kit->id("config:{$s}:{$key}"), 'adoptedAt' => $adopted, 'provisionedAt' => $adopted,
                'features' => $features, 'interfaces' => $interfaces,
            ] + $extra);
        };

        $gateway = $make('gateway', 'Gateway', 'UXG-Pro', ['gateway' => new \stdClass], ['ports' => $this->ports(4, 10000)]);
        $coreA = $make('core-a', 'Core Switch A', 'USW-Pro-Aggregation', ['switching' => ['lags' => [$lagCore]]], ['ports' => $this->ports(32, 10000)], [
            'uplink' => ['deviceId' => $gateway->uuid],
            'switchingRoles' => ['lags' => [$lagCore => [25, 26]], 'mcLag' => ['domainId' => $mcLag, 'name' => 'Core', 'role' => 'TOP', 'linkPortIdxs' => [31, 32]]],
        ]);
        $coreB = $make('core-b', 'Core Switch B', 'USW-Pro-Aggregation', ['switching' => ['lags' => [$lagCore]]], ['ports' => $this->ports(32, 10000)], [
            'uplink' => ['deviceId' => $gateway->uuid],
            'switchingRoles' => ['lags' => [$lagCore => [25, 26]], 'mcLag' => ['domainId' => $mcLag, 'name' => 'Core', 'role' => 'BOTTOM', 'linkPortIdxs' => [31, 32]]],
        ]);
        $accessA = $make('access-a', 'Floor 1 Switch A', 'USW-Pro-48-PoE', ['switching' => ['lags' => [$lagStack]]], ['ports' => $this->ports(52, 1000, true)], [
            'uplink' => ['deviceId' => $coreA->uuid],
            'switchingRoles' => ['lags' => [$lagStack => [49, 50]], 'stack' => $stack],
        ]);
        $accessB = $make('access-b', 'Floor 1 Switch B', 'USW-Pro-48-PoE', ['switching' => ['lags' => [$lagStack]]], ['ports' => $this->ports(52, 1000, true)], [
            'uplink' => ['deviceId' => $coreB->uuid],
            'switchingRoles' => ['lags' => [$lagStack => [49, 50]], 'stack' => $stack],
        ]);
        $ap = $make('ap-lobby', 'Lobby AP', 'U7-Pro', ['accessPoint' => new \stdClass], [
            'ports' => $this->ports(1, 2500, false),
            'radios' => [
                ['channelWidthMHz' => 40, 'frequencyGHz' => 2.4, 'wlanStandard' => 'IEEE_802_11AX', 'channel' => 6],
                ['channelWidthMHz' => 80, 'frequencyGHz' => 5, 'wlanStandard' => 'IEEE_802_11AX', 'channel' => 36],
                ['channelWidthMHz' => 160, 'frequencyGHz' => 6, 'wlanStandard' => 'IEEE_802_11BE', 'channel' => 37],
            ],
        ], ['uplink' => ['deviceId' => $accessA->uuid]]);

        return compact('gateway', 'coreA', 'coreB', 'accessA', 'accessB', 'ap');
    }

    /** One pending device, waiting to be adopted into any site. */
    public function pendingDevice(string $key, string $model, int $n): MockResource
    {
        return $this->kit->add(ResourceType::PendingDevice, null, $key, [
            'macAddress' => $this->kit->mac(), 'ipAddress' => ScenarioKit::ip(200 + $n, 2), 'model' => $model,
            'state' => 'PENDING_ADOPTION', 'supported' => true, 'firmwareVersion' => '7.0.50', 'firmwareUpdatable' => true,
            'features' => ['accessPoint' => new \stdClass],
            'interfaces' => ['ports' => $this->ports(1, 2500, false), 'radios' => [
                ['channelWidthMHz' => 40, 'frequencyGHz' => 2.4, 'wlanStandard' => 'IEEE_802_11AX', 'channel' => 1],
                ['channelWidthMHz' => 80, 'frequencyGHz' => 5, 'wlanStandard' => 'IEEE_802_11AX', 'channel' => 44],
            ]],
        ]);
    }

    private function ports(int $count, int $maxSpeed, bool $poe = false): array
    {
        $ports = [];
        for ($i = 1; $i <= $count; $i++) {
            $up = $i <= max(1, intdiv($count, 3));
            $ports[] = array_filter([
                'idx' => $i,
                'connector' => $maxSpeed >= 10000 ? 'SFPPLUS' : 'RJ45',
                'maxSpeedMbps' => $maxSpeed,
                'state' => $up ? 'UP' : 'DOWN',
                'speedMbps' => $up ? $maxSpeed : null,
                'poe' => $poe ? ['standard' => 'IEEE_802_3_AT', 'enabled' => $up] : null,
            ], fn ($v) => $v !== null);
        }

        return $ports;
    }

    private function firewall(string $s, array $nets, MockResource $lab): void
    {
        $zone = fn (string $key, string $name, array $networkIds, bool $system) => $this->kit->add(ResourceType::FirewallZone, $s, $key, [
            'name' => $name, 'networkIds' => $networkIds,
            'metadata' => ['origin' => $system ? 'SYSTEM_DEFINED' : 'USER_DEFINED', 'configurable' => ! $system],
        ]);
        $internal = $zone('internal', 'Internal', [$nets['lan']->uuid, $nets['members']->uuid], true);
        $external = $zone('external', 'External', [], true);
        $hotspot = $zone('hotspot', 'Hotspot', [$nets['guest']->uuid], true);
        $zone('lab', 'Lab', [$lab->uuid], false);

        $policy = function (string $key, string $name, MockResource $from, MockResource $to, string $action, bool $system, string $group, array $extra = []) use ($s) {
            return $this->kit->add(ResourceType::FirewallPolicy, $s, $key, [
                'name' => $name, 'enabled' => true, 'action' => ['type' => $action],
                'source' => ['zoneId' => $from->uuid], 'destination' => ['zoneId' => $to->uuid],
                'ipProtocolScope' => ['ipVersion' => 'IPV4_AND_IPV6'], 'loggingEnabled' => false,
                'metadata' => ['origin' => $system ? 'SYSTEM_DEFINED' : 'USER_DEFINED'],
                'orderGroup' => $group,
            ] + $extra);
        };
        $policy('guest-block-internal', 'Guests cannot reach members', $hotspot, $internal, 'BLOCK', false, 'before', [
            'description' => 'Keeps hotspot guests off the member and office networks.',
        ]);
        $policy('guest-allow-dns', 'Guests may resolve names', $hotspot, $internal, 'ALLOW', false, 'before', [
            'connectionStateFilter' => ['RELATED'],
        ]);
        $policy('system-guest-default', 'Hotspot to internal (default)', $hotspot, $internal, 'BLOCK', true, 'system');
        $policy('guest-log-rest', 'Log remaining guest traffic', $hotspot, $internal, 'BLOCK', false, 'after', ['loggingEnabled' => true]);
        $policy('internal-to-external', 'Members to internet', $internal, $external, 'ALLOW', false, 'before', [
            'schedule' => ['mode' => 'ALWAYS'],
        ]);
    }

    private function acl(string $s, array $nets): void
    {
        $rule = fn (string $key, array $payload) => $this->kit->add(ResourceType::AclRule, $s, $key, $payload + [
            'metadata' => ['origin' => 'USER_DEFINED'],
        ]);
        $rule('block-guest-printer', [
            'name' => 'Block guests from the printer', 'enabled' => true, 'type' => 'IPV4', 'action' => 'BLOCK',
            'description' => 'Guests on the hotspot cannot reach the shared printer.',
            'sourceFilter' => ['type' => 'NETWORK_ID', 'networkIds' => [$nets['guest']->uuid]],
            'destinationFilter' => ['type' => 'IP_ADDRESS', 'ipAddressesOrSubnets' => [ScenarioKit::ip(24)], 'portFilter' => [9100]],
            'protocolFilter' => ['TCP'], 'networkId' => $nets['guest']->uuid,
        ]);
        $rule('allow-members-display', [
            'name' => 'Allow members to cast to displays', 'enabled' => true, 'type' => 'IPV4', 'action' => 'ALLOW',
            'sourceFilter' => ['type' => 'NETWORK_ID', 'networkIds' => [$nets['members']->uuid]],
            'protocolFilter' => ['TCP', 'UDP'], 'networkId' => $nets['members']->uuid,
        ]);
        $rule('block-lab-mac', [
            'name' => 'Block a lost laptop', 'enabled' => false, 'type' => 'MAC', 'action' => 'BLOCK',
            'sourceFilter' => ['type' => 'MAC', 'macAddresses' => [$this->kit->mac()]],
            'enforcingDeviceFilter' => ['type' => 'ALL_DEVICES'],
        ]);
    }

    private function dns(string $s): void
    {
        $dns = fn (string $key, array $payload) => $this->kit->add(ResourceType::DnsPolicy, $s, $key, $payload + ['metadata' => ['origin' => 'USER_DEFINED']]);
        $dns('portal-a', ['type' => 'A_RECORD', 'enabled' => true, 'domain' => 'portal.apex.example', 'ipv4Address' => ScenarioKit::ip(10), 'ttlSeconds' => 300]);
        $dns('printer-cname', ['type' => 'CNAME_RECORD', 'enabled' => true, 'domain' => 'print.apex.example', 'targetDomain' => 'printer-1.apex.example', 'ttlSeconds' => 3600]);
        $dns('mail-mx', ['type' => 'MX_RECORD', 'enabled' => false, 'domain' => 'apex.example', 'mailServerDomain' => 'mail.apex.example', 'priority' => 10]);
        $dns('sip-srv', ['type' => 'SRV_RECORD', 'enabled' => true, 'domain' => 'apex.example', 'serverDomain' => 'sip.apex.example', 'service' => '_sip', 'protocol' => '_tcp', 'port' => 5060, 'priority' => 10, 'weight' => 5]);
        $dns('verify-txt', ['type' => 'TXT_RECORD', 'enabled' => true, 'domain' => 'apex.example', 'text' => 'v=example-verification']);

        $this->kit->add(ResourceType::TrafficMatchingList, $s, 'web-ports', ['name' => 'Web ports', 'type' => 'PORTS', 'items' => [80, 443]]);
        $this->kit->add(ResourceType::TrafficMatchingList, $s, 'office-ips', ['name' => 'Office printers', 'type' => 'IPV4_ADDRESSES', 'items' => [ScenarioKit::ip(24)]]);
    }

    /** @param array<string, MockResource> $devices */
    private function references(string $s, array $devices): void
    {
        $user = ['origin' => 'USER_DEFINED'];
        $this->kit->add(ResourceType::DeviceTag, $s, 'core', ['name' => 'Core', 'deviceIds' => [$devices['coreA']->uuid, $devices['coreB']->uuid], 'metadata' => $user]);
        $this->kit->add(ResourceType::DeviceTag, $s, 'floor-1', ['name' => 'Floor 1', 'deviceIds' => [$devices['accessA']->uuid, $devices['accessB']->uuid, $devices['ap']->uuid], 'metadata' => $user]);
        $this->kit->add(ResourceType::RadiusProfile, $s, 'default', ['name' => 'Default', 'metadata' => ['origin' => 'SYSTEM_DEFINED']]);
        $this->kit->add(ResourceType::RadiusProfile, $s, 'staff', ['name' => 'Staff 802.1X', 'metadata' => $user]);
        $this->kit->add(ResourceType::VpnServer, $s, 'wireguard', ['name' => 'Remote staff', 'type' => 'WIREGUARD', 'enabled' => true, 'metadata' => $user]);
        $this->kit->add(ResourceType::VpnServer, $s, 'l2tp', ['name' => 'Legacy L2TP', 'type' => 'L2TP', 'enabled' => false, 'metadata' => $user]);
        $this->kit->add(ResourceType::S2sTunnel, $s, 'branch', ['name' => 'To branch office', 'type' => 'IPSEC', 'metadata' => ['origin' => 'USER_DEFINED', 'source' => 'MANUAL']]);
        $this->kit->add(ResourceType::Wan, $s, 'wan1', ['name' => 'Primary internet']);
        $this->kit->add(ResourceType::Wan, $s, 'wan2', ['name' => 'Backup LTE']);
    }
}

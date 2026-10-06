<?php

namespace Database\Seeders\Scenarios;

use App\Enums\ResourceType;
use App\Mock\Scenarios\ScenarioKit;
use App\Models\MockResource;

/**
 * Builds one site's worth of invented, hand-authored data (D5) — nothing is copied from the
 * collection's machine-generated examples (FR-011). Every scenario but `empty` uses it.
 */
final class SiteBuilder
{
    public function __construct(private readonly ScenarioKit $kit) {}

    public function site(string $name, string $reference): MockResource
    {
        return $this->kit->add(ResourceType::Site, null, $reference, ['internalReference' => $reference, 'name' => $name]);
    }

    /**
     * Networks, broadcasts (one hotspot) and clients.
     *
     * @return array{lan: MockResource, members: MockResource, guest: MockResource, hotspot: MockResource}
     */
    public function network(MockResource $site, int $block = 0): array
    {
        $s = $site->uuid;
        $lan = $this->kit->add(ResourceType::Network, $s, 'default', [
            'name' => 'Default', 'enabled' => true, 'management' => 'GATEWAY', 'vlanId' => 1, 'default' => true,
            'metadata' => ['origin' => 'SYSTEM_DEFINED'],
        ]);
        $members = $this->kit->add(ResourceType::Network, $s, 'members', [
            'name' => 'Members', 'enabled' => true, 'management' => 'GATEWAY', 'vlanId' => 20, 'default' => false,
            'metadata' => ['origin' => 'USER_DEFINED'],
            'dhcpGuarding' => ['trustedDhcpServerIpAddresses' => [ScenarioKit::ip(1, $block)]],
        ]);
        $guest = $this->kit->add(ResourceType::Network, $s, 'guest', [
            'name' => 'Guest', 'enabled' => true, 'management' => 'GATEWAY', 'vlanId' => 30, 'default' => false,
            'metadata' => ['origin' => 'USER_DEFINED'],
        ]);

        $this->kit->add(ResourceType::WifiBroadcast, $s, 'members', $this->broadcast('APEX Members', 'WPA2_PERSONAL', $members->uuid));
        $hotspot = $this->kit->add(ResourceType::WifiBroadcast, $s, 'guest', $this->broadcast('APEX Guest', 'OPEN', $guest->uuid) + [
            'hotspotConfiguration' => ['type' => 'HOTSPOT'],
        ]);

        $clients = [
            ['Reception PC', 'WIRED', $lan, true, 'DEFAULT'],
            ['Meeting Room Display', 'WIRED', $lan, true, 'DEFAULT'],
            ['Member Laptop', 'WIRELESS', $members, true, 'DEFAULT'],
            ['Member Phone', 'WIRELESS', $members, true, 'DEFAULT'],
            ['Printer', 'WIRED', $lan, true, 'DEFAULT'],
            ['Visitor Phone', 'WIRELESS', $guest, false, 'GUEST'],
        ];
        foreach ($clients as $i => [$name, $type, $net, $authorized, $access]) {
            $this->kit->add(ResourceType::Client, $s, $name, [
                'name' => $name, 'type' => $type, 'macAddress' => $this->kit->mac(), 'ipAddress' => ScenarioKit::ip(20 + $i, $block),
                'connectedAt' => $this->kit->ago(30 + $i * 17), 'access' => ['type' => $access, 'authorized' => $authorized],
                'networkId' => $net->uuid,
            ]);
        }

        return compact('lan', 'members', 'guest', 'hotspot');
    }

    /** Five vouchers, one in each state: unused, active, full, expired, unlimited. */
    public function voucherStates(MockResource $site): void
    {
        $s = $site->uuid;
        $this->voucher($s, 'unused', '4815162342', 60, limit: 1, count: 0, activatedMinutesAgo: null);
        $this->voucher($s, 'active', '1029384756', 60, limit: 2, count: 1, activatedMinutesAgo: 10);
        $this->voucher($s, 'full', '5647382910', 120, limit: 1, count: 1, activatedMinutesAgo: 20);
        $this->voucher($s, 'expired', '9182736450', 60, limit: 1, count: 1, activatedMinutesAgo: 2 * 24 * 60);
        $this->voucher($s, 'unlimited', '3141592653', 1440, limit: null, count: 3, activatedMinutesAgo: 90, extra: [
            'dataUsageLimitMBytes' => 2048, 'rxRateLimitKbps' => 20000, 'txRateLimitKbps' => 5000,
        ]);
    }

    /** $n unused vouchers with sequential codes. */
    public function manyVouchers(MockResource $site, int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            $this->voucher($site->uuid, "bulk-{$i}", sprintf('7%09d', $i), 60 * (1 + $i % 4), limit: 1 + $i % 3, count: 0, activatedMinutesAgo: null, name: sprintf('Event pass %03d', $i));
        }
    }

    private function voucher(string $site, string $key, string $code, int $minutes, ?int $limit, int $count, ?int $activatedMinutesAgo, array $extra = [], ?string $name = null): void
    {
        $this->kit->add(ResourceType::Voucher, $site, $key, [
            'name' => $name ?? ucfirst($key).' guest',
            'code' => $code,
            'timeLimitMinutes' => $minutes,
            'authorizedGuestLimit' => $limit,
            'authorizedGuestCount' => $count,
            'createdAt' => $this->kit->ago(($activatedMinutesAgo ?? 0) + 60),
            'activatedAt' => $activatedMinutesAgo === null ? null : $this->kit->ago($activatedMinutesAgo),
        ] + $extra);
    }

    private function broadcast(string $name, string $security, string $networkUuid): array
    {
        return [
            'name' => $name, 'type' => 'STANDARD', 'enabled' => true,
            'channel2gLockedTo6' => false, 'clientIsolationEnabled' => $security === 'OPEN', 'dtimPeriod2gLockedTo3' => false,
            'hideName' => false, 'multicastToUnicastConversionEnabled' => true, 'uapsdEnabled' => true,
            'securityConfiguration' => ['type' => $security],
            'network' => ['type' => 'SPECIFIC', 'networkId' => $networkUuid],
            'broadcastingDeviceFilter' => ['type' => 'ALL_DEVICES'],
            'broadcastingFrequenciesGHz' => [2.4, 5],
            'metadata' => ['origin' => 'USER_DEFINED'],
        ];
    }
}

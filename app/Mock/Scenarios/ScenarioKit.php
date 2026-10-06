<?php

namespace App\Mock\Scenarios;

use App\Enums\ResourceType;
use App\Mock\Store\ResourceStore;
use App\Models\MockResource;
use Carbon\CarbonImmutable;
use Ramsey\Uuid\Uuid;

/**
 * Helpers scenarios build with. Ids are deterministic (UUID v5 of type + site + name), so a
 * reset to the same scenario gives the same ids every time (SC-008) and a Postman
 * `siteId` stays valid across resets. All data is invented (D5): RFC 5737 addresses and
 * locally administered MAC addresses only.
 */
final class ScenarioKit
{
    private const NAMESPACE = '6b1f4a52-0c7d-4e8f-9a3b-2d5c6e7f8a90';

    private int $macCounter = 0;

    public function __construct(
        public readonly ResourceStore $store,
        public readonly CarbonImmutable $now,
    ) {}

    public function id(string $name): string
    {
        // Not keyed on the scenario: the default site keeps its id in every scenario, so a
        // Postman `siteId` stays valid after switching scenario.
        return Uuid::uuid5(self::NAMESPACE, $name)->toString();
    }

    public function add(ResourceType $type, ?string $siteUuid, string $name, array $payload): MockResource
    {
        return $this->store->create($type, $siteUuid, $payload, $this->id($type->value.':'.$siteUuid.':'.$name));
    }

    /** A locally administered unicast MAC (02:…): never a real vendor's. */
    public function mac(): string
    {
        $n = ++$this->macCounter;

        return sprintf('02:a7:%02x:%02x:%02x:%02x', ($n >> 24) & 0xFF, ($n >> 16) & 0xFF, ($n >> 8) & 0xFF, $n & 0xFF);
    }

    /** An RFC 5737 documentation address (192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24). */
    public static function ip(int $n, int $block = 0): string
    {
        return ['192.0.2.', '198.51.100.', '203.0.113.'][$block % 3].(($n % 253) + 1);
    }

    public function iso(CarbonImmutable $at): string
    {
        return $at->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    public function ago(int $minutes): string
    {
        return $this->iso($this->now->subMinutes($minutes));
    }
}

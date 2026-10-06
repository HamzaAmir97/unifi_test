<?php

namespace App\Mock\Devices;

use App\Enums\ResourceType;
use App\Mock\Store\ResourceStore;
use App\Models\MockResource;
use Illuminate\Support\Collection;

/**
 * LAGs, MC-LAG domains and switch stacks are DERIVED from the site's devices (data-model §2),
 * never stored: removing a device changes them at once. Each device's `switchingRoles`
 * says which LAGs it belongs to (with ports), its MC-LAG role, and its stack.
 *
 * The objects are returned as unsaved MockResource instances so lists, filters and
 * presenters treat them like stored resources.
 */
final class SwitchingTopology
{
    public function __construct(private readonly ResourceStore $store) {}

    /** @return Collection<int, MockResource> */
    public function lags(string $siteUuid): Collection
    {
        $devices = $this->switches($siteUuid);
        $lags = [];
        foreach ($devices as $device) {
            foreach ($device->payload['switchingRoles']['lags'] ?? [] as $lagId => $ports) {
                $lags[$lagId]['members'][] = ['deviceId' => $device->uuid, 'portIdxs' => $ports];
                $lags[$lagId]['stack'][] = $device->payload['switchingRoles']['stack']['id'] ?? null;
                $lags[$lagId]['mcLag'][] = $device->payload['switchingRoles']['mcLag']['domainId'] ?? null;
            }
        }

        return collect($lags)->map(function ($lag, $id) {
            $stack = array_unique($lag['stack']);
            $mcLag = array_unique($lag['mcLag']);

            return $this->transient($id, array_filter([
                'members' => $lag['members'],
                'metadata' => ['origin' => 'USER_DEFINED'],
                'type' => 'LACP',
                'switchStackId' => count($stack) === 1 ? $stack[0] : null,
                'mcLagDomainId' => count($mcLag) === 1 ? $mcLag[0] : null,
            ], fn ($v) => $v !== null));
        })->values();
    }

    /** @return Collection<int, MockResource> */
    public function mcLagDomains(string $siteUuid): Collection
    {
        $domains = [];
        foreach ($this->switches($siteUuid) as $device) {
            $role = $device->payload['switchingRoles']['mcLag'] ?? null;
            if ($role !== null) {
                $domains[$role['domainId']]['name'] = $role['name'];
                $domains[$role['domainId']]['peers'][] = ['deviceId' => $device->uuid, 'linkPortIdxs' => $role['linkPortIdxs'], 'role' => $role['role']];
            }
        }

        return collect($domains)->map(fn ($d, $id) => $this->transient($id, [
            'lags' => $this->lagsWhere($siteUuid, 'mcLagDomainId', $id),
            'metadata' => ['origin' => 'USER_DEFINED'],
            'name' => $d['name'],
            'peers' => $d['peers'],
        ]))->values();
    }

    /** @return Collection<int, MockResource> */
    public function switchStacks(string $siteUuid): Collection
    {
        $stacks = [];
        foreach ($this->switches($siteUuid) as $device) {
            $stack = $device->payload['switchingRoles']['stack'] ?? null;
            if ($stack !== null) {
                $stacks[$stack['id']]['name'] = $stack['name'];
                $stacks[$stack['id']]['members'][] = ['deviceId' => $device->uuid];
            }
        }

        return collect($stacks)->map(fn ($s, $id) => $this->transient($id, [
            'lags' => $this->lagsWhere($siteUuid, 'switchStackId', $id),
            'members' => $s['members'],
            'metadata' => ['origin' => 'USER_DEFINED'],
            'name' => $s['name'],
        ]))->values();
    }

    private function lagsWhere(string $siteUuid, string $key, string $id): array
    {
        return $this->lags($siteUuid)
            ->filter(fn ($lag) => ($lag->payload[$key] ?? null) === $id)
            ->map(fn ($lag) => ['id' => $lag->uuid, 'members' => $lag->payload['members'], 'metadata' => $lag->payload['metadata']])
            ->values()->all();
    }

    /** @return Collection<int, MockResource> */
    private function switches(string $siteUuid): Collection
    {
        return $this->store->all(ResourceType::Device, $siteUuid)->filter(fn ($d) => isset($d->payload['switchingRoles']));
    }

    private function transient(string $uuid, array $payload): MockResource
    {
        $resource = new MockResource;
        $resource->uuid = $uuid;
        $resource->payload = $payload;

        return $resource;
    }
}

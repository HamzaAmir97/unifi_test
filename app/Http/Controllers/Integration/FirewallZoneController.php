<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Http\Requests\FirewallZoneRequest;
use App\Mock\Errors\MockError;
use App\Mock\Presenters\PayloadPresenter;
use App\Mock\Unverified;
use App\Models\MockResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Firewall zones. System zones (`metadata.configurable = false`) cannot be edited or deleted. */
class FirewallZoneController extends IntegrationController
{
    private function presenter(): PayloadPresenter
    {
        return PayloadPresenter::for('get-firewall-zone');
    }

    public function index(Request $request, string $siteId): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, 'list-firewall-zones', $this->store->all(ResourceType::FirewallZone, $siteId), $this->presenter());
    }

    public function store(FirewallZoneRequest $request, string $siteId): JsonResponse
    {
        $this->site($siteId);
        $input = $this->validated($request, $siteId);

        $zone = $this->store->create(ResourceType::FirewallZone, $siteId, $input + [
            'metadata' => ['origin' => 'USER_DEFINED', 'configurable' => true],
        ]);

        return $this->created($this->presenter()->present($zone));
    }

    public function show(string $siteId, string $firewallZoneId): JsonResponse
    {
        return new JsonResponse($this->presenter()->present($this->findIn(ResourceType::FirewallZone, $siteId, $firewallZoneId, 'Firewall zone')));
    }

    public function update(FirewallZoneRequest $request, string $siteId, string $firewallZoneId): JsonResponse
    {
        $zone = $this->editable($siteId, $firewallZoneId);
        $this->store->replace($zone, $this->validated($request, $siteId) + ['metadata' => $zone->payload['metadata']]);

        return new JsonResponse($this->presenter()->present($zone->fresh()));
    }

    public function destroy(string $siteId, string $firewallZoneId): Response
    {
        $zone = $this->editable($siteId, $firewallZoneId);
        $used = $this->store->all(ResourceType::FirewallPolicy, $siteId)->contains(
            fn ($p) => in_array($zone->uuid, [$p->payload['source']['zoneId'] ?? null, $p->payload['destination']['zoneId'] ?? null], true),
        );
        if ($used) {
            throw MockError::conflict('The zone is used by a firewall policy.', Unverified::ZONE_IN_USE_STATUS);
        }
        $this->store->delete($zone);

        return $this->emptyText();
    }

    private function editable(string $siteId, string $id): MockResource
    {
        $zone = $this->findIn(ResourceType::FirewallZone, $siteId, $id, 'Firewall zone');
        if (! ($zone->payload['metadata']['configurable'] ?? false)) {
            throw MockError::withStatus(Unverified::SYSTEM_ZONE_EDIT_STATUS, 'A system-defined firewall zone cannot be changed.');
        }

        return $zone;
    }

    private function validated(FirewallZoneRequest $request, string $siteId): array
    {
        $input = $request->validated();
        foreach ($input['networkIds'] as $networkId) {
            if (! $this->store->exists(ResourceType::Network, $siteId, $networkId)) {
                throw MockError::invalid("networkIds: {$networkId} is not a network of this site.");
            }
        }

        return ['name' => $input['name'], 'networkIds' => array_values($input['networkIds'])];
    }
}

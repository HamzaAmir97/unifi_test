<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Http\Requests\NetworkRequest;
use App\Mock\Errors\MockError;
use App\Mock\Presenters\NetworkPresenter;
use App\Mock\Unverified;
use App\Models\MockResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** Networks group. `vlanId` is unique per site; the default network cannot be deleted. */
class NetworkController extends IntegrationController
{
    public function index(Request $request, string $siteId, NetworkPresenter $presenter): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, 'list-networks', $this->store->all(ResourceType::Network, $siteId), $presenter, fn ($n) => $presenter->summary($n));
    }

    public function store(NetworkRequest $request, string $siteId, NetworkPresenter $presenter): JsonResponse
    {
        $this->site($siteId);
        $input = $request->validated();
        $this->assertVlanFree($siteId, (int) $input['vlanId']);

        $network = $this->store->create(ResourceType::Network, $siteId, $this->payload($input) + [
            'default' => false,
            'metadata' => ['origin' => 'USER_DEFINED'],
        ]);

        return $this->created($presenter->present($network));
    }

    public function show(string $siteId, string $networkId, NetworkPresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->present($this->findIn(ResourceType::Network, $siteId, $networkId, 'Network')));
    }

    /** PUT — full replace of the writable fields; `default` and `metadata` belong to the system. */
    public function update(NetworkRequest $request, string $siteId, string $networkId, NetworkPresenter $presenter): JsonResponse
    {
        $network = $this->findIn(ResourceType::Network, $siteId, $networkId, 'Network');
        $input = $request->validated();
        $this->assertVlanFree($siteId, (int) $input['vlanId'], $network->uuid);

        $this->store->replace($network, $this->payload($input) + [
            'default' => $network->payload['default'],
            'metadata' => $network->payload['metadata'],
        ]);

        return new JsonResponse($presenter->present($network->fresh()));
    }

    /** DELETE ?force= — refused while referenced unless force=true; the default network never. */
    public function destroy(Request $request, string $siteId, string $networkId): Response
    {
        $network = $this->findIn(ResourceType::Network, $siteId, $networkId, 'Network');
        $force = $this->force($request);

        if ($network->payload['default']) {
            throw MockError::conflict('The default network cannot be deleted.', Unverified::DEFAULT_NETWORK_DELETE_STATUS);
        }

        $blocking = array_filter($this->referenceGroups($siteId, $network), fn ($r) => in_array($r['resourceType'], ['WIFI_BROADCAST', 'FIREWALL_ZONE'], true));
        if ($blocking !== [] && ! $force) {
            throw MockError::conflict('The network is still referenced; delete with force=true to detach it.', Unverified::NETWORK_IN_USE_STATUS);
        }

        DB::transaction(function () use ($network, $siteId) {
            $this->detach($siteId, $network->uuid);
            $this->store->delete($network);
        });

        return $this->emptyText();
    }

    /** GET …/references — derived on every read, never stored. */
    public function references(string $siteId, string $networkId): JsonResponse
    {
        $network = $this->findIn(ResourceType::Network, $siteId, $networkId, 'Network');

        return new JsonResponse(['referenceResources' => $this->referenceGroups($siteId, $network)]);
    }

    /** @return list<array{referenceCount: int, resourceType: string, references: list<array{id: string}>}> */
    private function referenceGroups(string $siteId, MockResource $network): array
    {
        $groups = [
            'WIFI_BROADCAST' => $this->store->all(ResourceType::WifiBroadcast, $siteId)->filter(fn ($b) => ($b->payload['network']['networkId'] ?? null) === $network->uuid),
            'FIREWALL_ZONE' => $this->store->all(ResourceType::FirewallZone, $siteId)->filter(fn ($z) => in_array($network->uuid, $z->payload['networkIds'] ?? [], true)),
            'CLIENT' => $this->store->all(ResourceType::Client, $siteId)->filter(fn ($c) => ($c->payload['networkId'] ?? null) === $network->uuid),
        ];

        $out = [];
        foreach ($groups as $type => $items) {
            if ($items->isNotEmpty()) {
                $out[] = [
                    'referenceCount' => $items->count(),
                    'resourceType' => $type,
                    'references' => $items->map(fn ($r) => ['id' => $r->uuid])->values()->all(),
                ];
            }
        }

        return $out;
    }

    private function detach(string $siteId, string $networkUuid): void
    {
        foreach ($this->store->all(ResourceType::WifiBroadcast, $siteId) as $b) {
            if (($b->payload['network']['networkId'] ?? null) === $networkUuid) {
                $payload = $b->payload;
                unset($payload['network']);
                $this->store->replace($b, $payload);
            }
        }
        foreach ($this->store->all(ResourceType::FirewallZone, $siteId) as $z) {
            if (in_array($networkUuid, $z->payload['networkIds'] ?? [], true)) {
                $this->store->patch($z, ['networkIds' => array_values(array_diff($z->payload['networkIds'], [$networkUuid]))]);
            }
        }
    }

    private function payload(array $input): array
    {
        return array_filter([
            'name' => $input['name'],
            'enabled' => (bool) $input['enabled'],
            'management' => $input['management'],
            'vlanId' => (int) $input['vlanId'],
            'dhcpGuarding' => $input['dhcpGuarding'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function assertVlanFree(string $siteId, int $vlanId, ?string $except = null): void
    {
        $taken = $this->store->all(ResourceType::Network, $siteId)
            ->contains(fn ($n) => $n->uuid !== $except && (int) $n->payload['vlanId'] === $vlanId);
        if ($taken) {
            throw MockError::withStatus(Unverified::DUPLICATE_VLAN_STATUS, "vlanId {$vlanId} is already used in this site.");
        }
    }
}

<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Http\Requests\FirewallOrderingRequest;
use App\Http\Requests\FirewallPolicyRequest;
use App\Http\Requests\PatchFirewallPolicyRequest;
use App\Mock\Errors\MockError;
use App\Mock\Presenters\PayloadPresenter;
use App\Mock\Unverified;
use App\Models\MockResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Firewall policies. `index` is assigned by the mock (the stored position). User-defined
 * policies are ordered per source/destination zone pair, before or after the system-defined
 * ones; the ordering endpoints read and rewrite that order.
 */
class FirewallPolicyController extends IntegrationController
{
    private function presenter(): PayloadPresenter
    {
        return PayloadPresenter::for('get-firewall-policy');
    }

    public function index(Request $request, string $siteId): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, 'list-firewall-policies', $this->store->all(ResourceType::FirewallPolicy, $siteId), $this->presenter());
    }

    public function store(FirewallPolicyRequest $request, string $siteId): JsonResponse
    {
        $this->site($siteId);
        $payload = $this->checked($request->validated(), $siteId);

        $policy = $this->store->create(ResourceType::FirewallPolicy, $siteId, $payload + [
            'metadata' => ['origin' => 'USER_DEFINED'],
            'orderGroup' => 'before',
        ]);

        return $this->created($this->presenter()->present($policy));
    }

    public function show(string $siteId, string $firewallPolicyId): JsonResponse
    {
        return new JsonResponse($this->presenter()->present($this->policy($siteId, $firewallPolicyId)));
    }

    public function update(FirewallPolicyRequest $request, string $siteId, string $firewallPolicyId): JsonResponse
    {
        $policy = $this->policy($siteId, $firewallPolicyId);
        $this->store->replace($policy, $this->checked($request->validated(), $siteId) + [
            'metadata' => $policy->payload['metadata'],
            'orderGroup' => $policy->payload['orderGroup'] ?? 'before',
        ]);

        return new JsonResponse($this->presenter()->present($policy->fresh()));
    }

    /** PATCH — merge: only the fields sent change. */
    public function patch(PatchFirewallPolicyRequest $request, string $siteId, string $firewallPolicyId): JsonResponse
    {
        $policy = $this->policy($siteId, $firewallPolicyId);
        $changes = $request->validated();
        $this->checked(array_replace($policy->payload, $changes), $siteId);
        $this->store->patch($policy, $changes);

        return new JsonResponse($this->presenter()->present($policy->fresh()));
    }

    public function destroy(string $siteId, string $firewallPolicyId): Response
    {
        $this->store->delete($this->policy($siteId, $firewallPolicyId));

        return $this->emptyText();
    }

    /** GET …/policies/ordering?sourceFirewallZoneId=&destinationFirewallZoneId= */
    public function ordering(Request $request, string $siteId): JsonResponse
    {
        $this->site($siteId);
        [$source, $destination] = $this->zonePair($request, $siteId);

        return new JsonResponse($this->orderingBody($this->userDefined($siteId, $source, $destination)));
    }

    /** PUT …/policies/ordering — must hold exactly the pair's user-defined policy ids. */
    public function reorder(FirewallOrderingRequest $request, string $siteId): JsonResponse
    {
        $this->site($siteId);
        [$source, $destination] = $this->zonePair($request, $siteId);
        $policies = $this->userDefined($siteId, $source, $destination);

        $before = $request->validated()['orderedFirewallPolicyIds']['beforeSystemDefined'];
        $after = $request->validated()['orderedFirewallPolicyIds']['afterSystemDefined'];
        $given = [...$before, ...$after];
        $current = $policies->pluck('uuid')->all();
        sort($current);
        $sorted = $given;
        sort($sorted);
        if ($sorted !== $current || count(array_unique($given)) !== count($given)) {
            throw MockError::withStatus(Unverified::ORDERING_MISMATCH_STATUS, 'The ordering must contain exactly the user-defined policy ids of this zone pair.');
        }

        DB::transaction(function () use ($policies, $before, $after) {
            $positions = $policies->pluck('position')->sort()->values()->all();
            $byId = $policies->keyBy('uuid');
            foreach ([...$before, ...$after] as $i => $id) {
                $policy = $byId[$id];
                $policy->position = $positions[$i];
                $policy->payload = ['orderGroup' => in_array($id, $before, true) ? 'before' : 'after'] + $policy->payload;
                $policy->save();
            }
        });

        return new JsonResponse($this->orderingBody($this->userDefined($siteId, $source, $destination)));
    }

    private function orderingBody(Collection $policies): array
    {
        $ids = fn (string $group) => $policies->filter(fn ($p) => ($p->payload['orderGroup'] ?? 'before') === $group)->sortBy('position')->pluck('uuid')->values()->all();

        return ['orderedFirewallPolicyIds' => ['afterSystemDefined' => $ids('after'), 'beforeSystemDefined' => $ids('before')]];
    }

    /** @return Collection<int, MockResource> */
    private function userDefined(string $siteId, string $source, string $destination): Collection
    {
        return $this->store->all(ResourceType::FirewallPolicy, $siteId)->filter(fn ($p) => ($p->payload['metadata']['origin'] ?? '') !== 'SYSTEM_DEFINED'
            && ($p->payload['source']['zoneId'] ?? null) === $source
            && ($p->payload['destination']['zoneId'] ?? null) === $destination)->values();
    }

    /** @return array{0: string, 1: string} */
    private function zonePair(Request $request, string $siteId): array
    {
        $pair = [];
        foreach (['sourceFirewallZoneId', 'destinationFirewallZoneId'] as $key) {
            $id = $request->query($key);
            if (! is_string($id) || $id === '') {
                throw MockError::invalid("{$key} is required.");
            }
            if (! $this->store->exists(ResourceType::FirewallZone, $siteId, $id)) {
                throw MockError::notFound("Firewall zone {$id} not found.");
            }
            $pair[] = $id;
        }

        return $pair;
    }

    private function policy(string $siteId, string $id): MockResource
    {
        return $this->findIn(ResourceType::FirewallPolicy, $siteId, $id, 'Firewall policy');
    }

    /** Source and destination zones must exist in the site. */
    private function checked(array $payload, string $siteId): array
    {
        foreach (['source', 'destination'] as $side) {
            $zoneId = $payload[$side]['zoneId'] ?? null;
            if ($zoneId !== null && ! $this->store->exists(ResourceType::FirewallZone, $siteId, $zoneId)) {
                throw MockError::invalid("{$side}.zoneId {$zoneId} is not a firewall zone of this site.");
            }
        }

        return array_filter($payload, fn ($v) => $v !== null);
    }
}

<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Http\Requests\DnsPolicyRequest;
use App\Mock\Presenters\PayloadPresenter;
use App\Mock\Unverified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** DNS policies. Which fields a policy keeps depends on its `type` (G4). */
class DnsPolicyController extends IntegrationController
{
    private const ALL_FIELDS = ['domain', 'ipv4Address', 'ipv6Address', 'targetDomain', 'mailServerDomain', 'text', 'serverDomain',
        'ipAddress', 'ttlSeconds', 'priority', 'service', 'protocol', 'port', 'weight'];

    private function presenter(): PayloadPresenter
    {
        return PayloadPresenter::for('get-dns-policy', extra: self::ALL_FIELDS);
    }

    public function index(Request $request, string $siteId): JsonResponse
    {
        $this->site($siteId);
        $presenter = PayloadPresenter::for('list-dns-policies', 'data[]', self::ALL_FIELDS);

        return $this->listResponse($request, 'list-dns-policies', $this->store->all(ResourceType::DnsPolicy, $siteId), $presenter);
    }

    public function store(DnsPolicyRequest $request, string $siteId): JsonResponse
    {
        $this->site($siteId);
        $policy = $this->store->create(ResourceType::DnsPolicy, $siteId, $this->payload($request) + ['metadata' => ['origin' => 'USER_DEFINED']]);

        return $this->created($this->presenter()->present($policy));
    }

    public function show(string $siteId, string $dnsPolicyId): JsonResponse
    {
        return new JsonResponse($this->presenter()->present($this->findIn(ResourceType::DnsPolicy, $siteId, $dnsPolicyId, 'DNS policy')));
    }

    public function update(DnsPolicyRequest $request, string $siteId, string $dnsPolicyId): JsonResponse
    {
        $policy = $this->findIn(ResourceType::DnsPolicy, $siteId, $dnsPolicyId, 'DNS policy');
        $this->store->replace($policy, $this->payload($request) + ['metadata' => $policy->payload['metadata']]);

        return new JsonResponse($this->presenter()->present($policy->fresh()));
    }

    public function destroy(string $siteId, string $dnsPolicyId): Response
    {
        $this->store->delete($this->findIn(ResourceType::DnsPolicy, $siteId, $dnsPolicyId, 'DNS policy'));

        return $this->emptyText();
    }

    /** Keep `enabled`, `type`, and the fields that type uses (all sent fields for an unknown type). */
    private function payload(DnsPolicyRequest $request): array
    {
        $input = array_filter($request->validated(), fn ($v) => $v !== null);
        $applies = Unverified::DNS_FIELDS_BY_TYPE[$input['type']] ?? self::ALL_FIELDS;

        return ['enabled' => (bool) $input['enabled'], 'type' => $input['type']] + array_intersect_key($input, array_flip($applies));
    }
}

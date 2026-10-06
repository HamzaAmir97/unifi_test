<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Http\Requests\AclOrderingRequest;
use App\Http\Requests\AclRuleRequest;
use App\Mock\Errors\MockError;
use App\Mock\Presenters\PayloadPresenter;
use App\Mock\Unverified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** ACL rules. `index` is the rule's position in the site's user-defined ordering. */
class AclRuleController extends IntegrationController
{
    private function presenter(): PayloadPresenter
    {
        return PayloadPresenter::for('get-acl-rule');
    }

    public function index(Request $request, string $siteId): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, 'list-acl-rules', $this->store->all(ResourceType::AclRule, $siteId), $this->presenter());
    }

    public function store(AclRuleRequest $request, string $siteId): JsonResponse
    {
        $this->site($siteId);
        $rule = $this->store->create(ResourceType::AclRule, $siteId, $this->payload($request) + ['metadata' => ['origin' => 'USER_DEFINED']]);

        return $this->created($this->presenter()->present($rule));
    }

    public function show(string $siteId, string $aclRuleId): JsonResponse
    {
        return new JsonResponse($this->presenter()->present($this->findIn(ResourceType::AclRule, $siteId, $aclRuleId, 'ACL rule')));
    }

    public function update(AclRuleRequest $request, string $siteId, string $aclRuleId): JsonResponse
    {
        $rule = $this->findIn(ResourceType::AclRule, $siteId, $aclRuleId, 'ACL rule');
        $this->store->replace($rule, $this->payload($request) + ['metadata' => $rule->payload['metadata']]);

        return new JsonResponse($this->presenter()->present($rule->fresh()));
    }

    public function destroy(string $siteId, string $aclRuleId): Response
    {
        $this->store->delete($this->findIn(ResourceType::AclRule, $siteId, $aclRuleId, 'ACL rule'));

        return $this->emptyText();
    }

    public function ordering(string $siteId): JsonResponse
    {
        $this->site($siteId);

        return new JsonResponse(['orderedAclRuleIds' => $this->store->all(ResourceType::AclRule, $siteId)->pluck('uuid')->values()->all()]);
    }

    /** PUT — must hold exactly the site's rule ids; positions are reused in the new order. */
    public function reorder(AclOrderingRequest $request, string $siteId): JsonResponse
    {
        $this->site($siteId);
        $rules = $this->store->all(ResourceType::AclRule, $siteId);
        $given = $request->validated()['orderedAclRuleIds'];
        $current = $rules->pluck('uuid')->all();
        $a = $given;
        sort($a);
        sort($current);
        if ($a !== $current || count(array_unique($given)) !== count($given)) {
            throw MockError::withStatus(Unverified::ORDERING_MISMATCH_STATUS, 'The ordering must contain exactly the ACL rule ids of this site.');
        }

        DB::transaction(function () use ($rules, $given) {
            $positions = $rules->pluck('position')->sort()->values()->all();
            $byId = $rules->keyBy('uuid');
            foreach ($given as $i => $id) {
                $byId[$id]->position = $positions[$i];
                $byId[$id]->save();
            }
        });

        return $this->ordering($siteId);
    }

    /** The body's optional `index` is not honoured: the mock assigns positions (UNVERIFIED). */
    private function payload(AclRuleRequest $request): array
    {
        $payload = $request->validated();
        unset($payload['index']);

        return array_filter($payload, fn ($v) => $v !== null);
    }
}

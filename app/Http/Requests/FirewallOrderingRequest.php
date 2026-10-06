<?php

namespace App\Http\Requests;

class FirewallOrderingRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return 'reorder-user-defined-firewall-policies';
    }

    protected function extraRules(): array
    {
        return [
            'orderedFirewallPolicyIds.beforeSystemDefined.*' => ['uuid'],
            'orderedFirewallPolicyIds.afterSystemDefined.*' => ['uuid'],
        ];
    }
}

<?php

namespace App\Http\Requests;

class FirewallPolicyRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return $this->isMethod('PUT') ? 'update-firewall-policy' : 'create-firewall-policy';
    }

    protected function extraRules(): array
    {
        return [
            'connectionStateFilter.*' => ['string'],
        ];
    }
}

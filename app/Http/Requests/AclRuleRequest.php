<?php

namespace App\Http\Requests;

class AclRuleRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return $this->isMethod('PUT') ? 'update-acl-rule' : 'create-acl-rule';
    }

    protected function extraRules(): array
    {
        return [
            'protocolFilter' => ['sometimes', 'array'],
            'networkId' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}

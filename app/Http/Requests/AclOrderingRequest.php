<?php

namespace App\Http\Requests;

class AclOrderingRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return 'reorder-user-defined-acl-rules';
    }

    protected function extraRules(): array
    {
        return [
            'orderedAclRuleIds.*' => ['uuid'],
        ];
    }
}

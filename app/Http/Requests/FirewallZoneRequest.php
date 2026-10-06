<?php

namespace App\Http\Requests;

class FirewallZoneRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return $this->isMethod('PUT') ? 'update-firewall-zone' : 'create-custom-firewall-zone';
    }

    protected function extraRules(): array
    {
        return [
            'networkIds.*' => ['uuid'],
        ];
    }
}

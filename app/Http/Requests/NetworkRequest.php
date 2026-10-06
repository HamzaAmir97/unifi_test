<?php

namespace App\Http\Requests;

/** Create (POST) and full replace (PUT) share one body. */
class NetworkRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return $this->isMethod('PUT') ? 'update-network' : 'create-network';
    }

    protected function extraRules(): array
    {
        return [
            'vlanId' => ['required', 'integer', 'between:1,4094'],
            'dhcpGuarding.trustedDhcpServerIpAddresses.*' => ['ip'],
        ];
    }
}

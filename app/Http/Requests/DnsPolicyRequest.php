<?php

namespace App\Http\Requests;

/**
 * The collection's body shows only `{enabled, type}`; the fields a type uses are the
 * list's filterable properties (UNVERIFIED G4, Unverified::DNS_FIELDS_BY_TYPE).
 */
class DnsPolicyRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return $this->isMethod('PUT') ? 'update-dns-policy' : 'create-dns-policy';
    }

    protected function extraRules(): array
    {
        $string = ['sometimes', 'nullable', 'string', 'max:255'];
        $integer = ['sometimes', 'nullable', 'integer', 'min:0'];

        return [
            'domain' => $string, 'targetDomain' => $string, 'mailServerDomain' => $string, 'text' => $string,
            'serverDomain' => $string, 'service' => $string, 'protocol' => $string,
            'ipv4Address' => ['sometimes', 'nullable', 'ipv4'], 'ipv6Address' => ['sometimes', 'nullable', 'ipv6'],
            'ipAddress' => ['sometimes', 'nullable', 'ip'],
            'ttlSeconds' => $integer, 'priority' => $integer, 'weight' => $integer,
            'port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
        ];
    }
}

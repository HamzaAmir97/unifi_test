<?php

namespace App\Http\Requests;

class TrafficMatchingListRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return $this->isMethod('PUT') ? 'update-traffic-matching-list' : 'create-traffic-matching-list';
    }

    protected function extraRules(): array
    {
        return [
            'items' => ['sometimes', 'array'],
        ];
    }
}

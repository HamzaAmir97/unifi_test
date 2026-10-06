<?php

namespace App\Http\Requests;

/**
 * Create (POST) and full replace (PUT). `network.networkId` and `hotspotConfiguration` are
 * not in the collection's (truncated) body example but are filterable properties of the
 * list, so they are accepted here (UNVERIFIED).
 */
class WifiBroadcastRequest extends ManifestFormRequest
{
    protected function entry(): string
    {
        return $this->isMethod('PUT') ? 'update-wifi-broadcast' : 'create-wifi-broadcast';
    }

    protected function extraRules(): array
    {
        return [
            'network.networkId' => ['sometimes', 'nullable', 'uuid'],
            'hotspotConfiguration' => ['sometimes', 'nullable', 'array'],
            'hotspotConfiguration.type' => ['required_with:hotspotConfiguration', 'string'],
            'broadcastingFrequenciesGHz' => ['sometimes', 'array'],
            'broadcastingFrequenciesGHz.*' => ['numeric'],
            'broadcastingDeviceFilter.deviceIds' => ['sometimes', 'array'],
            'broadcastingDeviceFilter.deviceTagIds' => ['sometimes', 'array'],
        ];
    }
}

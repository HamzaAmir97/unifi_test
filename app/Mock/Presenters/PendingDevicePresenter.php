<?php

namespace App\Mock\Presenters;

use App\Enums\ResourceType;
use App\Mock\Store\ResourceStore;
use App\Models\MockResource;

/** Pending devices are global and carry no id; any existing site is an adoption target. */
class PendingDevicePresenter extends Presenter
{
    protected function fieldsFrom(): array
    {
        return ['list-devices-pending-adoption', 'data[]'];
    }

    protected function values(MockResource $resource): array
    {
        $p = $resource->payload;

        return [
            'adoptionTargetSiteIds' => app(ResourceStore::class)->all(ResourceType::Site)->pluck('uuid')->all(),
            'features' => array_keys($p['features'] ?? []),
            'firmwareUpdatable' => (bool) $p['firmwareUpdatable'],
            'ipAddress' => $p['ipAddress'],
            'macAddress' => $p['macAddress'],
            'model' => $p['model'],
            'state' => $p['state'],
            'supported' => (bool) $p['supported'],
            'firmwareVersion' => $p['firmwareVersion'] ?? null,
        ];
    }
}

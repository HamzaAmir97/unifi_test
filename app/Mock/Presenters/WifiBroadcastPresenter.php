<?php

namespace App\Mock\Presenters;

use App\Models\MockResource;

/**
 * Every field name the collection shows, including at the truncation point (FR-012b).
 * `hotspotConfiguration` is not in the example's field set but is a filterable property and
 * marks the hotspot SSID, so it is returned when present (UNVERIFIED).
 */
class WifiBroadcastPresenter extends Presenter
{
    protected function fieldsFrom(): array
    {
        return ['get-wifi-broadcast-details', ''];
    }

    protected function extraKeys(): array
    {
        return ['hotspotConfiguration', 'broadcastingFrequenciesGHz'];
    }

    protected function values(MockResource $resource): array
    {
        return ['id' => $resource->uuid] + $resource->payload;
    }

    public function summary(MockResource $resource): array
    {
        return $this->presentAs($resource, 'list-wifi-broadcasts', 'data[]');
    }
}

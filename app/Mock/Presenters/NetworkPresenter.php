<?php

namespace App\Mock\Presenters;

use App\Models\MockResource;

class NetworkPresenter extends Presenter
{
    protected function fieldsFrom(): array
    {
        return ['get-network-details', ''];
    }

    protected function values(MockResource $resource): array
    {
        $p = $resource->payload;

        return [
            'default' => (bool) $p['default'],
            'enabled' => (bool) $p['enabled'],
            'id' => $resource->uuid,
            'management' => $p['management'],
            'metadata' => $p['metadata'],
            'name' => $p['name'],
            'vlanId' => (int) $p['vlanId'],
            'dhcpGuarding' => $p['dhcpGuarding'] ?? null,
            'deviceId' => $p['deviceId'] ?? null, // filterable on the list; not part of the response object
        ];
    }

    /** The list returns the summary object (no dhcpGuarding). */
    public function summary(MockResource $resource): array
    {
        return $this->presentAs($resource, 'list-networks', 'data[]');
    }
}

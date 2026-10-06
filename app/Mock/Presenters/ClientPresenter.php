<?php

namespace App\Mock\Presenters;

use App\Models\MockResource;

/** `access` is `{type, authorized}` (G12). The voucher link is internal and never returned. */
class ClientPresenter extends Presenter
{
    protected function fieldsFrom(): array
    {
        return ['get-connected-client-details', ''];
    }

    protected function values(MockResource $resource): array
    {
        $p = $resource->payload;

        return [
            'access' => $p['access'],
            'id' => $resource->uuid,
            'name' => $p['name'],
            'type' => $p['type'],
            'connectedAt' => self::iso($p['connectedAt'] ?? null),
            'ipAddress' => $p['ipAddress'] ?? null,
            'macAddress' => $p['macAddress'] ?? null, // filterable; not part of the response object
        ];
    }
}

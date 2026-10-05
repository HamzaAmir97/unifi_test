<?php

namespace App\Mock\Presenters;

use App\Models\MockResource;

class SitePresenter extends Presenter
{
    protected function fieldsFrom(): array
    {
        return ['list-local-sites', 'data[]'];
    }

    protected function values(MockResource $resource): array
    {
        return [
            'id' => $resource->uuid,
            'internalReference' => $resource->payload['internalReference'],
            'name' => $resource->payload['name'],
        ];
    }
}

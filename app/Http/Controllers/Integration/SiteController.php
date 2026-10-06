<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Mock\Presenters\SitePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends IntegrationController
{
    /** GET /v1/sites — the collection ships `filter` disabled here; a filter that is sent is still honoured. */
    public function index(Request $request, SitePresenter $presenter): JsonResponse
    {
        return $this->listResponse($request, 'list-local-sites', $this->store->all(ResourceType::Site), $presenter);
    }
}

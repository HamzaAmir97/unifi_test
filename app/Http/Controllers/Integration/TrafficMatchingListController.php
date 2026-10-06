<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Http\Requests\TrafficMatchingListRequest;
use App\Mock\Presenters\PayloadPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrafficMatchingListController extends IntegrationController
{
    private function presenter(): PayloadPresenter
    {
        return PayloadPresenter::for('get-traffic-matching-list');
    }

    public function index(Request $request, string $siteId): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, 'list-traffic-matching-lists', $this->store->all(ResourceType::TrafficMatchingList, $siteId), $this->presenter());
    }

    public function store(TrafficMatchingListRequest $request, string $siteId): JsonResponse
    {
        $this->site($siteId);

        return $this->created($this->presenter()->present($this->store->create(ResourceType::TrafficMatchingList, $siteId, $request->validated())));
    }

    public function show(string $siteId, string $trafficMatchingListId): JsonResponse
    {
        return new JsonResponse($this->presenter()->present($this->findIn(ResourceType::TrafficMatchingList, $siteId, $trafficMatchingListId, 'Traffic matching list')));
    }

    public function update(TrafficMatchingListRequest $request, string $siteId, string $trafficMatchingListId): JsonResponse
    {
        $list = $this->findIn(ResourceType::TrafficMatchingList, $siteId, $trafficMatchingListId, 'Traffic matching list');
        $this->store->replace($list, $request->validated());

        return new JsonResponse($this->presenter()->present($list->fresh()));
    }

    public function destroy(string $siteId, string $trafficMatchingListId): Response
    {
        $this->store->delete($this->findIn(ResourceType::TrafficMatchingList, $siteId, $trafficMatchingListId, 'Traffic matching list'));

        return $this->emptyText();
    }
}

<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Http\Requests\WifiBroadcastRequest;
use App\Mock\Errors\MockError;
use App\Mock\Presenters\WifiBroadcastPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** WiFi Broadcasts group. `network.networkId`, when given, must be a network of the same site. */
class WifiBroadcastController extends IntegrationController
{
    public function index(Request $request, string $siteId, WifiBroadcastPresenter $presenter): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, 'list-wifi-broadcasts', $this->store->all(ResourceType::WifiBroadcast, $siteId), $presenter, fn ($b) => $presenter->summary($b));
    }

    public function store(WifiBroadcastRequest $request, string $siteId, WifiBroadcastPresenter $presenter): JsonResponse
    {
        $this->site($siteId);
        $payload = $this->payload($request, $siteId);

        $broadcast = $this->store->create(ResourceType::WifiBroadcast, $siteId, $payload + ['metadata' => ['origin' => 'USER_DEFINED']]);

        return $this->created($presenter->present($broadcast));
    }

    public function show(string $siteId, string $wifiBroadcastId, WifiBroadcastPresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->present($this->findIn(ResourceType::WifiBroadcast, $siteId, $wifiBroadcastId, 'WiFi broadcast')));
    }

    public function update(WifiBroadcastRequest $request, string $siteId, string $wifiBroadcastId, WifiBroadcastPresenter $presenter): JsonResponse
    {
        $broadcast = $this->findIn(ResourceType::WifiBroadcast, $siteId, $wifiBroadcastId, 'WiFi broadcast');
        $this->store->replace($broadcast, $this->payload($request, $siteId) + ['metadata' => $broadcast->payload['metadata']]);

        return new JsonResponse($presenter->present($broadcast->fresh()));
    }

    /** DELETE ?force= — nothing references a broadcast in the mock, so force only needs to be valid. */
    public function destroy(Request $request, string $siteId, string $wifiBroadcastId): Response
    {
        $broadcast = $this->findIn(ResourceType::WifiBroadcast, $siteId, $wifiBroadcastId, 'WiFi broadcast');
        $this->force($request);
        $this->store->delete($broadcast);

        return $this->emptyText();
    }

    private function payload(WifiBroadcastRequest $request, string $siteId): array
    {
        $payload = $request->validated();
        $networkId = $payload['network']['networkId'] ?? null;
        if ($networkId !== null && ! $this->store->exists(ResourceType::Network, $siteId, $networkId)) {
            throw MockError::invalid("network.networkId {$networkId} is not a network of this site.");
        }

        return array_filter($payload, fn ($v) => $v !== null);
    }
}

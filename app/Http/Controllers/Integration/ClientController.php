<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Http\Requests\ClientActionRequest;
use App\Mock\Presenters\ClientPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Clients group. A client action answers `{"action": …}` (FR-009); action names are UNVERIFIED (G5). */
class ClientController extends IntegrationController
{
    public function index(Request $request, string $siteId, ClientPresenter $presenter): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, 'list-connected-clients', $this->store->all(ResourceType::Client, $siteId), $presenter);
    }

    public function show(string $siteId, string $clientId, ClientPresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->present($this->findIn(ResourceType::Client, $siteId, $clientId, 'Client')));
    }

    public function action(ClientActionRequest $request, string $siteId, string $clientId): JsonResponse
    {
        $client = $this->findIn(ResourceType::Client, $siteId, $clientId, 'Client');
        $action = $request->validated()['action'];

        $authorized = match ($action) {
            'AUTHORIZE_GUEST_ACCESS' => true,
            'UNAUTHORIZE_GUEST_ACCESS' => false,
            default => null, // unknown: accepted with a warning (EnumWarden), no state change
        };
        if ($authorized !== null) {
            $this->store->patch($client, ['access' => ['type' => $client->payload['access']['type'] ?? 'GUEST', 'authorized' => $authorized]]);
        }

        return new JsonResponse(['action' => $action]);
    }
}

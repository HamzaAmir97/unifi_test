<?php

namespace App\Http\Controllers\Control;

use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Mock\Errors\MockError;
use App\Mock\Presenters\ClientPresenter;
use App\Mock\Presenters\PendingDevicePresenter;
use App\Mock\Store\ResourceStore;
use App\Mock\Unverified;
use App\Models\MockResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Client connect/disconnect and a new pending device: things the network does on its own. */
class SimulationController extends Controller
{
    private const MAC = 'regex:/^([0-9a-fA-F]{2}:){5}[0-9a-fA-F]{2}$/';

    public function __construct(private readonly ResourceStore $store) {}

    /** POST /__mock/clients/connect */
    public function connect(Request $request, ClientPresenter $presenter): JsonResponse
    {
        $input = $request->validate([
            'siteId' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:128'],
            'type' => ['required', 'string', 'in:WIRED,WIRELESS,VPN'],
            'macAddress' => ['sometimes', 'nullable', 'string', self::MAC],
            'ipAddress' => ['sometimes', 'nullable', 'ip'],
        ]);
        $this->assertSite($input['siteId']);

        $client = $this->store->create(ResourceType::Client, $input['siteId'], [
            'name' => $input['name'],
            'type' => $input['type'],
            'macAddress' => $input['macAddress'] ?? sprintf('02:5c:%02x:%02x:%02x:%02x', random_int(0, 255), random_int(0, 255), random_int(0, 255), random_int(0, 255)),
            'ipAddress' => $input['ipAddress'] ?? null,
            'connectedAt' => CarbonImmutable::now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'access' => ['type' => 'DEFAULT', 'authorized' => true],
        ]);

        return new JsonResponse($presenter->present($client), 201);
    }

    /** POST /__mock/clients/disconnect */
    public function disconnect(Request $request): JsonResponse
    {
        $input = $request->validate(['siteId' => ['required', 'uuid'], 'clientId' => ['required', 'uuid']]);
        $this->assertSite($input['siteId']);
        $client = $this->store->find(ResourceType::Client, $input['siteId'], $input['clientId'])
            ?? throw MockError::notFound("Client {$input['clientId']} not found.");
        $this->store->delete($client);

        return new JsonResponse(['disconnected' => $client->uuid]);
    }

    /** POST /__mock/devices/pending */
    public function pendingDevice(Request $request, PendingDevicePresenter $presenter): JsonResponse
    {
        $input = $request->validate([
            'macAddress' => ['required', 'string', self::MAC],
            'model' => ['required', 'string', 'max:64'],
            'ipAddress' => ['sometimes', 'nullable', 'ip'],
        ]);
        $mac = strtolower($input['macAddress']);
        $taken = $this->store->all(ResourceType::PendingDevice)->contains(fn ($d) => strtolower($d->payload['macAddress']) === $mac)
            || MockResource::query()->where('type', ResourceType::Device->value)->get()->contains(fn ($d) => strtolower($d->payload['macAddress']) === $mac);
        if ($taken) {
            throw MockError::conflict("A device with MAC {$mac} already exists.", Unverified::DUPLICATE_MAC_STATUS);
        }

        $device = $this->store->create(ResourceType::PendingDevice, null, [
            'macAddress' => $mac, 'ipAddress' => $input['ipAddress'] ?? '198.51.100.250', 'model' => $input['model'],
            'state' => 'PENDING_ADOPTION', 'supported' => true, 'firmwareVersion' => '7.0.50', 'firmwareUpdatable' => true,
            'features' => ['accessPoint' => []], 'interfaces' => ['ports' => [['idx' => 1, 'connector' => 'RJ45', 'maxSpeedMbps' => 1000, 'state' => 'UP', 'speedMbps' => 1000]]],
        ]);

        return new JsonResponse($presenter->present($device), 201);
    }

    private function assertSite(string $siteId): void
    {
        if (! $this->store->exists(ResourceType::Site, null, $siteId)) {
            throw MockError::notFound("Site {$siteId} not found.");
        }
    }
}

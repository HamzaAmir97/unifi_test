<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Http\Requests\AdoptDeviceRequest;
use App\Http\Requests\DeviceActionRequest;
use App\Http\Requests\PortActionRequest;
use App\Mock\Devices\DeviceStatistics;
use App\Mock\Errors\MockError;
use App\Mock\Presenters\DevicePresenter;
use App\Mock\Presenters\PendingDevicePresenter;
use App\Mock\Unverified;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * UniFi Devices group. Adoption answers 200 (not 201) and moves the device from the
 * pending list into the site (FR-009). Device and port actions answer 200 with an empty
 * text/plain body.
 */
class DeviceController extends IntegrationController
{
    /** GET /v1/pending-devices — not site-scoped. */
    public function pending(Request $request, PendingDevicePresenter $presenter): JsonResponse
    {
        return $this->listResponse($request, 'list-devices-pending-adoption', $this->store->all(ResourceType::PendingDevice), $presenter);
    }

    public function index(Request $request, string $siteId, DevicePresenter $presenter): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, 'list-adopted-devices', $this->store->all(ResourceType::Device, $siteId), $presenter, fn ($d) => $presenter->summary($d));
    }

    /** POST …/devices — adopt by MAC; a MAC that is not pending is 404. */
    public function adopt(AdoptDeviceRequest $request, string $siteId, DevicePresenter $presenter): JsonResponse
    {
        $this->site($siteId);
        $mac = strtolower($request->validated()['macAddress']);
        $pending = $this->store->all(ResourceType::PendingDevice)->first(fn ($p) => strtolower($p->payload['macAddress']) === $mac)
            ?? throw MockError::notFound("No device pending adoption with MAC {$mac}.");

        $now = CarbonImmutable::now()->utc()->format('Y-m-d\TH:i:s.v\Z');
        $device = DB::transaction(function () use ($pending, $siteId, $now) {
            $p = $pending->payload;
            $this->store->delete($pending);

            return $this->store->create(ResourceType::Device, $siteId, [
                'name' => $p['model'].' '.substr(str_replace(':', '', $p['macAddress']), -4),
                'model' => $p['model'],
                'macAddress' => $p['macAddress'],
                'ipAddress' => $p['ipAddress'],
                'state' => Unverified::ADOPTED_DEVICE_STATE,
                'supported' => $p['supported'],
                'firmwareVersion' => $p['firmwareVersion'] ?? null,
                'firmwareUpdatable' => $p['firmwareUpdatable'],
                'configurationId' => (string) Str::uuid(),
                'adoptedAt' => $now,
                'provisionedAt' => $now,
                'features' => $p['features'] ?? [],
                'interfaces' => $p['interfaces'] ?? [],
            ]);
        });

        return new JsonResponse($presenter->present($device), 200);
    }

    /** DELETE …/devices/{deviceId} — unadopt. */
    public function destroy(string $siteId, string $deviceId): Response
    {
        $this->store->delete($this->findIn(ResourceType::Device, $siteId, $deviceId, 'Device'));

        return $this->emptyText();
    }

    public function show(string $siteId, string $deviceId, DevicePresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->present($this->findIn(ResourceType::Device, $siteId, $deviceId, 'Device')));
    }

    /** POST …/devices/{deviceId}/actions — RESTART puts the device in UPDATING for a while (G5). */
    public function action(DeviceActionRequest $request, string $siteId, string $deviceId): Response
    {
        $device = $this->findIn(ResourceType::Device, $siteId, $deviceId, 'Device');
        if ($request->validated()['action'] === 'RESTART') {
            $this->store->patch($device, ['restartedAt' => CarbonImmutable::now()->utc()->toIso8601ZuluString('millisecond')]);
        }

        return $this->emptyText();
    }

    /** POST …/interfaces/ports/{portIdx}/actions — an unknown port is 404. */
    public function portAction(PortActionRequest $request, string $siteId, string $deviceId, string $portIdx): Response
    {
        $device = $this->findIn(ResourceType::Device, $siteId, $deviceId, 'Device');
        if (! ctype_digit($portIdx)) {
            throw MockError::invalid('portIdx must be an integer.');
        }
        $ports = array_column($device->payload['interfaces']['ports'] ?? [], 'idx');
        if (! in_array((int) $portIdx, $ports, true)) {
            throw MockError::notFound("Port {$portIdx} not found on this device.");
        }
        $request->validated();

        return $this->emptyText();
    }

    public function statistics(string $siteId, string $deviceId): JsonResponse
    {
        return new JsonResponse(DeviceStatistics::for($this->findIn(ResourceType::Device, $siteId, $deviceId, 'Device')));
    }
}

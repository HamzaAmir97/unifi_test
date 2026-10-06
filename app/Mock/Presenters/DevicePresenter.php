<?php

namespace App\Mock\Presenters;

use App\Mock\Unverified;
use App\Models\MockResource;
use Carbon\CarbonImmutable;

/**
 * Adopted devices. Details carry `features` and `interfaces` as objects; the list carries
 * them as sets of names (`["switching"]`, `["ports"]`), which is also what the list's
 * filter properties declare (SET(STRING)). The state is UPDATING for a while after RESTART.
 */
class DevicePresenter extends Presenter
{
    protected function fieldsFrom(): array
    {
        return ['get-adopted-device-details', ''];
    }

    protected function values(MockResource $resource): array
    {
        $p = $resource->payload;

        return [
            'configurationId' => $p['configurationId'],
            'features' => self::objects($p['features'] ?? []),
            'firmwareUpdatable' => (bool) $p['firmwareUpdatable'],
            'id' => $resource->uuid,
            'interfaces' => self::objects($p['interfaces'] ?? []),
            'ipAddress' => $p['ipAddress'],
            'macAddress' => $p['macAddress'],
            'model' => $p['model'],
            'name' => $p['name'],
            'state' => self::state($p),
            'supported' => (bool) $p['supported'],
            'adoptedAt' => self::iso($p['adoptedAt'] ?? null),
            'firmwareVersion' => $p['firmwareVersion'] ?? null,
            'provisionedAt' => self::iso($p['provisionedAt'] ?? null),
            'uplink' => $p['uplink'] ?? null,
        ];
    }

    public function view(MockResource $resource): array
    {
        return $this->summaryValues($resource);
    }

    public function summary(MockResource $resource): array
    {
        return $this->shape($this->summaryValues($resource), 'list-adopted-devices', 'data[]');
    }

    private function summaryValues(MockResource $resource): array
    {
        $values = $this->values($resource);
        $values['features'] = array_keys($resource->payload['features'] ?? []);
        $values['interfaces'] = array_keys(array_filter($resource->payload['interfaces'] ?? []));

        return $values;
    }

    public static function state(array $payload): string
    {
        $restarted = $payload['restartedAt'] ?? null;
        if ($restarted !== null && CarbonImmutable::now()->lessThan(CarbonImmutable::parse($restarted)->addSeconds(Unverified::DEVICE_RESTART_SECONDS))) {
            return 'UPDATING';
        }

        return $payload['state'];
    }

    /** JSON storage turns `{}` into `[]`; a feature or interface map must serialize as an object. */
    private static function objects(array $map): object|array
    {
        if ($map === []) {
            return new \stdClass;
        }
        foreach ($map as $key => $value) {
            if ($value === []) {
                $map[$key] = new \stdClass;
            }
        }

        return $map;
    }
}

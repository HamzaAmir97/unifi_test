<?php

namespace App\Mock\Presenters;

use App\Mock\Hotspot\VoucherState;
use App\Models\MockResource;

/** `expired` and `expiresAt` are derived from the mock clock on every read (R9, FR-014). */
class VoucherPresenter extends Presenter
{
    protected function fieldsFrom(): array
    {
        return ['get-voucher-details', ''];
    }

    protected function values(MockResource $resource): array
    {
        $p = $resource->payload;

        return [
            'authorizedGuestCount' => (int) $p['authorizedGuestCount'],
            'code' => $p['code'],
            'createdAt' => self::iso($p['createdAt']),
            'expired' => VoucherState::expired($p),
            'id' => $resource->uuid,
            'name' => $p['name'],
            'timeLimitMinutes' => (int) $p['timeLimitMinutes'],
            'activatedAt' => self::iso($p['activatedAt'] ?? null),
            'authorizedGuestLimit' => $p['authorizedGuestLimit'] ?? null,
            'dataUsageLimitMBytes' => $p['dataUsageLimitMBytes'] ?? null,
            'expiresAt' => VoucherState::expiresAt($p)?->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'rxRateLimitKbps' => $p['rxRateLimitKbps'] ?? null,
            'txRateLimitKbps' => $p['txRateLimitKbps'] ?? null,
        ];
    }
}

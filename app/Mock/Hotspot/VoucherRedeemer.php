<?php

namespace App\Mock\Hotspot;

use App\Enums\ResourceType;
use App\Mock\Errors\MockError;
use App\Mock\Scenarios\ScenarioKit;
use App\Mock\Store\ResourceStore;
use App\Mock\Unverified;
use App\Models\MockResource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What the captive portal does when a guest types a code (spec fact 6, FR-016): activate on
 * first use, count the guest, create an authorized guest client. Refuses unknown, expired
 * and full vouchers. Deleting a voucher removes its guests' authorization.
 */
final class VoucherRedeemer
{
    public function __construct(private readonly ResourceStore $store) {}

    /** @return array{voucher: MockResource, client: MockResource} */
    public function redeem(string $siteUuid, string $code, ?string $macAddress = null, ?string $name = null): array
    {
        return DB::transaction(function () use ($siteUuid, $code, $macAddress, $name) {
            $voucher = $this->store->all(ResourceType::Voucher, $siteUuid)->first(fn ($v) => $v->payload['code'] === $code)
                ?? throw MockError::notFound("No voucher with code {$code} in this site.");

            if (VoucherState::expired($voucher->payload)) {
                throw MockError::conflict('The voucher has expired.', Unverified::REDEEM_EXPIRED_STATUS);
            }
            if (VoucherState::full($voucher->payload)) {
                throw MockError::conflict('The voucher has reached its guest limit.', Unverified::REDEEM_FULL_STATUS);
            }

            $now = CarbonImmutable::now()->utc()->format('Y-m-d\TH:i:s.v\Z');
            $payload = $voucher->payload;
            $payload['activatedAt'] ??= $now;
            $payload['authorizedGuestCount'] = (int) $payload['authorizedGuestCount'] + 1;
            $this->store->replace($voucher, $payload);

            $n = $this->store->all(ResourceType::Client, $siteUuid)->count() + 1;
            $client = $this->store->create(ResourceType::Client, $siteUuid, [
                'name' => $name ?? "Guest {$n}",
                'type' => 'WIRELESS',
                'macAddress' => $macAddress ?? sprintf('02:9e:%02x:%02x:%02x:%02x', random_int(0, 255), random_int(0, 255), random_int(0, 255), random_int(0, 255)),
                'ipAddress' => ScenarioKit::ip(100 + $n, 1),
                'connectedAt' => $now,
                'access' => Unverified::REDEEMED_CLIENT_ACCESS,
                'voucherId' => $voucher->uuid,
            ]);

            return ['voucher' => $voucher->fresh(), 'client' => $client];
        });
    }

    /** Guest clients of the deleted vouchers lose their authorization. @param list<string> $voucherUuids */
    public function revokeGuests(string $siteUuid, array $voucherUuids): void
    {
        foreach ($this->store->all(ResourceType::Client, $siteUuid) as $client) {
            if (in_array($client->payload['voucherId'] ?? null, $voucherUuids, true)) {
                $this->store->patch($client, ['access' => ['type' => $client->payload['access']['type'] ?? 'GUEST', 'authorized' => false]]);
            }
        }
    }
}

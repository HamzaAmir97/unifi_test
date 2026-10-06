<?php

namespace App\Mock\Hotspot;

use App\Mock\Unverified;
use Carbon\CarbonImmutable;

/**
 * The voucher's derived state (data-model §3). Nothing here is stored:
 * expiresAt = activatedAt + timeLimitMinutes; expired = expiresAt != null && now >= expiresAt.
 * An unused voucher never expires on the clock alone (G8).
 */
final class VoucherState
{
    public static function expiresAt(array $payload): ?CarbonImmutable
    {
        if (empty($payload['activatedAt'])) {
            return null;
        }

        return CarbonImmutable::parse($payload['activatedAt'])->addMinutes((int) $payload['timeLimitMinutes']);
    }

    public static function expired(array $payload, ?CarbonImmutable $now = null): bool
    {
        $expiresAt = self::expiresAt($payload);
        if ($expiresAt === null) {
            return Unverified::UNUSED_VOUCHER_EXPIRES;
        }

        return ($now ?? CarbonImmutable::now())->greaterThanOrEqualTo($expiresAt);
    }

    public static function full(array $payload): bool
    {
        $limit = $payload['authorizedGuestLimit'] ?? null;

        return $limit !== null && (int) $payload['authorizedGuestCount'] >= (int) $limit;
    }
}

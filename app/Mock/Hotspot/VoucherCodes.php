<?php

namespace App\Mock\Hotspot;

use App\Enums\ResourceType;
use App\Mock\Store\ResourceStore;
use App\Mock\Unverified;

/**
 * Voucher codes: Unverified::VOUCHER_CODE_LENGTH digits (G7), unique among the site's
 * vouchers, regenerated on collision (R14).
 */
final class VoucherCodes
{
    public function __construct(private readonly ResourceStore $store) {}

    /** @return list<string> $count new codes, distinct from each other and from the site's existing ones */
    public function generate(string $siteUuid, int $count): array
    {
        $taken = $this->store->all(ResourceType::Voucher, $siteUuid)->pluck('payload.code')->flip()->all();
        $codes = [];
        while (count($codes) < $count) {
            $code = '';
            for ($i = 0; $i < Unverified::VOUCHER_CODE_LENGTH; $i++) {
                $code .= random_int(0, 9);
            }
            if (! isset($taken[$code])) {
                $taken[$code] = true;
                $codes[] = $code;
            }
        }

        return $codes;
    }
}

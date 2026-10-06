<?php

namespace App\Http\Controllers\Control;

use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Mock\Errors\MockError;
use App\Mock\Hotspot\VoucherRedeemer;
use App\Mock\Presenters\ClientPresenter;
use App\Mock\Presenters\VoucherPresenter;
use App\Mock\Store\ResourceStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /__mock/hotspot/redeem — a guest typing the code at the captive portal (spec fact 6). */
class RedeemController extends Controller
{
    public function __invoke(Request $request, VoucherRedeemer $redeemer, ResourceStore $store, VoucherPresenter $vouchers, ClientPresenter $clients): JsonResponse
    {
        $input = $request->validate([
            'siteId' => ['required', 'uuid'],
            'code' => ['required', 'string'],
            'macAddress' => ['sometimes', 'nullable', 'string', 'regex:/^([0-9a-fA-F]{2}:){5}[0-9a-fA-F]{2}$/'],
            'name' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);
        if (! $store->exists(ResourceType::Site, null, $input['siteId'])) {
            throw MockError::notFound("Site {$input['siteId']} not found.");
        }

        $result = $redeemer->redeem($input['siteId'], $input['code'], $input['macAddress'] ?? null, $input['name'] ?? null);

        return new JsonResponse(['voucher' => $vouchers->present($result['voucher']), 'client' => $clients->present($result['client'])]);
    }
}

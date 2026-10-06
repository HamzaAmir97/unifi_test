<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Http\Requests\GenerateVouchersRequest;
use App\Mock\Hotspot\VoucherCodes;
use App\Mock\Hotspot\VoucherRedeemer;
use App\Mock\Presenters\VoucherPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Hotspot group: list, generate, get, delete one, delete by filter. */
class VoucherController extends IntegrationController
{
    /** GET …/hotspot/vouchers — default limit 100 (G6). */
    public function index(Request $request, string $siteId, VoucherPresenter $presenter): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, 'list-vouchers', $this->store->all(ResourceType::Voucher, $siteId), $presenter);
    }

    /** POST …/hotspot/vouchers — 201 with `count` vouchers, each with a distinct id and code (FR-013). */
    public function store(GenerateVouchersRequest $request, string $siteId, VoucherCodes $codes, VoucherPresenter $presenter): JsonResponse
    {
        $this->site($siteId);
        $input = $request->validated();
        $count = (int) ($input['count'] ?? 1);
        $now = CarbonImmutable::now()->utc()->format('Y-m-d\TH:i:s.v\Z');

        $created = DB::transaction(function () use ($siteId, $codes, $count, $input, $now) {
            $out = [];
            foreach ($codes->generate($siteId, $count) as $code) {
                $out[] = $this->store->create(ResourceType::Voucher, $siteId, [
                    'name' => $input['name'],
                    'code' => $code,
                    'timeLimitMinutes' => (int) $input['timeLimitMinutes'],
                    'authorizedGuestLimit' => $input['authorizedGuestLimit'] ?? null,
                    'authorizedGuestCount' => 0,
                    'dataUsageLimitMBytes' => $input['dataUsageLimitMBytes'] ?? null,
                    'rxRateLimitKbps' => $input['rxRateLimitKbps'] ?? null,
                    'txRateLimitKbps' => $input['txRateLimitKbps'] ?? null,
                    'createdAt' => $now,
                    'activatedAt' => null,
                ]);
            }

            return $out;
        });

        return $this->created(['vouchers' => array_map(fn ($v) => $presenter->presentAs($v, 'generate-vouchers', 'vouchers[]'), $created)]);
    }

    /** GET …/hotspot/vouchers/{voucherId} */
    public function show(string $siteId, string $voucherId, VoucherPresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->present($this->findIn(ResourceType::Voucher, $siteId, $voucherId, 'Voucher')));
    }

    /** DELETE …/hotspot/vouchers/{voucherId} — `{"vouchersDeleted": 1}`, not an empty body. */
    public function destroy(string $siteId, string $voucherId, VoucherRedeemer $redeemer): JsonResponse
    {
        $voucher = $this->findIn(ResourceType::Voucher, $siteId, $voucherId, 'Voucher');
        DB::transaction(function () use ($voucher, $siteId, $redeemer) {
            $this->store->delete($voucher);
            $redeemer->revokeGuests($siteId, [$voucher->uuid]);
        });

        return new JsonResponse(['vouchersDeleted' => 1]);
    }

    /** DELETE …/hotspot/vouchers?filter= — the filter is REQUIRED: without it, 400 and nothing deleted (FR-015, T3). */
    public function destroyMany(Request $request, string $siteId, VoucherPresenter $presenter, VoucherRedeemer $redeemer): JsonResponse
    {
        $this->site($siteId);
        $rows = array_map(
            fn ($v) => ['resource' => $v, 'view' => $presenter->view($v)],
            $this->store->all(ResourceType::Voucher, $siteId)->all(),
        );
        $matched = array_column($this->filtered($request, 'delete-vouchers', $rows, required: true), 'resource');

        $deleted = DB::transaction(function () use ($matched, $siteId, $redeemer) {
            $n = $this->store->deleteMany($matched);
            $redeemer->revokeGuests($siteId, array_map(fn ($v) => $v->uuid, $matched));

            return $n;
        });

        return new JsonResponse(['vouchersDeleted' => $deleted]);
    }
}

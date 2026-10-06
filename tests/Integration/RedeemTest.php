<?php

namespace Tests\Integration;

use App\Enums\ResourceType;
use App\Mock\Errors\MockError;
use App\Mock\Hotspot\VoucherRedeemer;
use App\Mock\Store\ResourceStore;
use App\Models\MockResource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedeemTest extends TestCase
{
    use RefreshDatabase;

    private MockResource $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->site = $this->seedSite();
    }

    private function voucher(?int $limit, int $count = 0, ?string $activatedAt = null, string $code = '1111111111'): MockResource
    {
        return app(ResourceStore::class)->create(ResourceType::Voucher, $this->site->uuid, [
            'name' => 'qa', 'code' => $code, 'timeLimitMinutes' => 60, 'authorizedGuestLimit' => $limit,
            'authorizedGuestCount' => $count, 'createdAt' => '2026-01-01T00:00:00.000Z', 'activatedAt' => $activatedAt,
        ]);
    }

    private function redeemer(): VoucherRedeemer
    {
        return app(VoucherRedeemer::class);
    }

    public function test_redeem_activates_counts_and_creates_an_authorized_guest(): void
    {
        $voucher = $this->voucher(2);

        $result = $this->redeemer()->redeem($this->site->uuid, '1111111111', '02:00:00:00:00:01', 'Visitor');

        $this->assertNotNull($result['voucher']->payload['activatedAt']);
        $this->assertSame(1, $result['voucher']->payload['authorizedGuestCount']);
        $this->assertSame(['type' => 'GUEST', 'authorized' => true], $result['client']->payload['access']);
        $this->assertSame($voucher->uuid, $result['client']->payload['voucherId']);
        $this->assertSame('Visitor', $result['client']->payload['name']);
    }

    public function test_a_second_redeem_keeps_the_first_activation_time(): void
    {
        $this->voucher(3);
        $first = $this->redeemer()->redeem($this->site->uuid, '1111111111')['voucher']->payload['activatedAt'];

        $second = $this->redeemer()->redeem($this->site->uuid, '1111111111')['voucher'];

        $this->assertSame($first, $second->payload['activatedAt']);
        $this->assertSame(2, $second->payload['authorizedGuestCount']);
    }

    public function test_refused_at_the_guest_limit_with_the_count_unchanged(): void
    {
        $voucher = $this->voucher(1, 1, CarbonImmutable::now()->toIso8601ZuluString());

        try {
            $this->redeemer()->redeem($this->site->uuid, '1111111111');
            $this->fail('Redeemed a full voucher');
        } catch (MockError $e) {
            $this->assertSame(409, $e->status);
        }
        $this->assertSame(1, $voucher->fresh()->payload['authorizedGuestCount']);
        $this->assertCount(0, app(ResourceStore::class)->all(ResourceType::Client, $this->site->uuid));
    }

    public function test_unlimited_when_no_limit_is_set(): void
    {
        $this->voucher(null);
        for ($i = 0; $i < 5; $i++) {
            $this->redeemer()->redeem($this->site->uuid, '1111111111');
        }

        $this->assertCount(5, app(ResourceStore::class)->all(ResourceType::Client, $this->site->uuid));
    }

    public function test_unknown_and_expired_codes_are_refused(): void
    {
        $this->voucher(1, 0, CarbonImmutable::now()->subDay()->toIso8601ZuluString(), '2222222222');

        foreach (['0000000000' => 404, '2222222222' => 409] as $code => $status) {
            try {
                $this->redeemer()->redeem($this->site->uuid, $code);
                $this->fail("Redeemed {$code}");
            } catch (MockError $e) {
                $this->assertSame($status, $e->status, (string) $code);
            }
        }
    }

    public function test_deleting_a_voucher_removes_its_guests_authorization(): void
    {
        $voucher = $this->voucher(null);
        $client = $this->redeemer()->redeem($this->site->uuid, '1111111111')['client'];

        $this->unifi('DELETE', "/v1/sites/{$this->site->uuid}/hotspot/vouchers/{$voucher->uuid}")->assertOk();

        $this->assertFalse($client->fresh()->payload['access']['authorized']);
    }
}

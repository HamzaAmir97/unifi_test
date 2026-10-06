<?php

namespace Tests\Unit\Presenters;

use App\Enums\ResourceType;
use App\Mock\Clock\MockClock;
use App\Mock\Presenters\VoucherPresenter;
use App\Mock\Settings\MockSettings;
use App\Mock\Store\ResourceStore;
use App\Models\MockResource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherPresenterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function voucher(?string $activatedAt, int $minutes = 60): MockResource
    {
        $site = $this->seedSite();

        return app(ResourceStore::class)->create(ResourceType::Voucher, $site->uuid, [
            'name' => 'qa', 'code' => '0123456789', 'timeLimitMinutes' => $minutes, 'authorizedGuestLimit' => 1,
            'authorizedGuestCount' => $activatedAt ? 1 : 0, 'createdAt' => '2026-05-01T07:00:00.000Z', 'activatedAt' => $activatedAt,
        ]);
    }

    private function presentAt(MockResource $voucher, string $now): array
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($now));

        return app(VoucherPresenter::class)->present($voucher);
    }

    public function test_before_activation_the_expiry_is_absent_and_it_never_expires(): void
    {
        $out = $this->presentAt($this->voucher(null), '2099-01-01T00:00:00Z');

        $this->assertArrayNotHasKey('activatedAt', $out);
        $this->assertArrayNotHasKey('expiresAt', $out);
        $this->assertFalse($out['expired'], 'an unused voucher never expires by clock alone (G8)');
    }

    public function test_null_mode_returns_them_as_null(): void
    {
        app(MockSettings::class)->put(MockSettings::OPTIONAL_FIELDS, 'null');

        $out = $this->presentAt($this->voucher(null), '2026-05-01T08:00:00Z');

        $this->assertNull($out['activatedAt']);
        $this->assertNull($out['expiresAt']);
    }

    public function test_expires_at_is_activation_plus_the_time_limit(): void
    {
        $out = $this->presentAt($this->voucher('2026-05-01T08:00:00.000Z', 90), '2026-05-01T08:10:00Z');

        $this->assertSame('2026-05-01T08:00:00.000Z', $out['activatedAt']);
        $this->assertSame('2026-05-01T09:30:00.000Z', $out['expiresAt']);
        $this->assertFalse($out['expired']);
    }

    public function test_expired_exactly_at_the_boundary(): void
    {
        $voucher = $this->voucher('2026-05-01T08:00:00.000Z', 60);

        $this->assertFalse($this->presentAt($voucher, '2026-05-01T08:59:59.999Z')['expired']);
        $this->assertTrue($this->presentAt($voucher, '2026-05-01T09:00:00.000Z')['expired']);
    }

    public function test_required_fields_come_first(): void
    {
        $out = $this->presentAt($this->voucher(null), '2026-05-01T08:00:00Z');

        $this->assertSame(['authorizedGuestCount', 'code', 'createdAt', 'expired', 'id', 'name', 'timeLimitMinutes', 'authorizedGuestLimit'], array_keys($out));
    }

    public function test_the_mock_clock_drives_expiry_in_requests(): void
    {
        $voucher = $this->voucher(MockClock::realNow()->subMinutes(30)->format('Y-m-d\TH:i:s.v\Z'), 60);
        $path = "/v1/sites/{$voucher->site_uuid}/hotspot/vouchers/{$voucher->uuid}";

        $this->assertFalse($this->unifi('GET', $path)->json('expired'));
        app(MockClock::class)->advanceMinutes(31);
        $this->assertTrue($this->unifi('GET', $path)->json('expired'));
    }
}

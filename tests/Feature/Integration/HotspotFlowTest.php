<?php

namespace Tests\Feature\Integration;

use App\Enums\ResourceType;
use App\Mock\Scenarios\ScenarioRunner;
use App\Models\MockResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** US2: the voucher lifecycle end to end, through the integration API and the control plane. */
class HotspotFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScenarioRunner::class)->run('default');
        $this->site = $this->unifi('GET', '/v1/sites')->json('data.0.id');
    }

    private function vouchers(string $query = ''): TestResponse
    {
        return $this->unifi('GET', "/v1/sites/{$this->site}/hotspot/vouchers{$query}");
    }

    public function test_the_eight_step_flow(): void
    {
        // 1. find the hotspot broadcast and its network
        $broadcasts = $this->unifi('GET', "/v1/sites/{$this->site}/wifi/broadcasts?filter=".urlencode("hotspotConfiguration.type.eq('HOTSPOT')"))->assertOk();
        $this->assertSame(1, $broadcasts->json('totalCount'));
        $this->assertSame('APEX Guest', $broadcasts->json('data.0.name'));

        // 2. generate three vouchers
        $generated = $this->unifi('POST', "/v1/sites/{$this->site}/hotspot/vouchers", [
            'name' => 'qa', 'timeLimitMinutes' => 60, 'count' => 3, 'authorizedGuestLimit' => 1,
        ])->assertCreated();
        $vouchers = $generated->json('vouchers');
        $this->assertCount(3, $vouchers);
        $this->assertCount(3, array_unique(array_column($vouchers, 'id')));
        $this->assertCount(3, array_unique(array_column($vouchers, 'code')));
        foreach ($vouchers as $v) {
            $this->assertMatchesRegularExpression('/^\d{10}$/', $v['code']);
            $this->assertArrayNotHasKey('activatedAt', $v);
            $this->assertArrayNotHasKey('expiresAt', $v);
            $this->assertSame(0, $v['authorizedGuestCount']);
        }

        // 3. they appear in the list
        $listed = array_column($this->vouchers('?limit=200')->json('data'), 'id');
        foreach ($vouchers as $v) {
            $this->assertContains($v['id'], $listed);
        }

        // 4. a guest redeems one at the captive portal
        $code = $vouchers[0]['code'];
        $redeemed = $this->control('POST', '/hotspot/redeem', ['siteId' => $this->site, 'code' => $code])->assertOk();
        $voucher = $this->unifi('GET', "/v1/sites/{$this->site}/hotspot/vouchers/{$vouchers[0]['id']}")->json();
        $this->assertNotNull($voucher['activatedAt']);
        $this->assertSame(1, $voucher['authorizedGuestCount']);
        $this->assertSame(strtotime($voucher['activatedAt']) + 3600, strtotime($voucher['expiresAt']));

        // 5. an authorized guest client appears
        $clientId = $redeemed->json('client.id');
        $guests = $this->unifi('GET', "/v1/sites/{$this->site}/clients?filter=".urlencode('access.authorized.eq(true)'))->json('data');
        $this->assertContains($clientId, array_column($guests, 'id'));

        // 6. the guest limit is reached: refused, count unchanged
        $this->assertEnvelope($this->control('POST', '/hotspot/redeem', ['siteId' => $this->site, 'code' => $code]), 409);
        $this->assertSame(1, $this->unifi('GET', "/v1/sites/{$this->site}/hotspot/vouchers/{$vouchers[0]['id']}")->json('authorizedGuestCount'));

        // 7. the clock passes the expiry
        $this->control('POST', '/clock', ['advanceMinutes' => 61])->assertOk();
        $this->assertTrue($this->unifi('GET', "/v1/sites/{$this->site}/hotspot/vouchers/{$vouchers[0]['id']}")->json('expired'));

        // 8. bulk delete without a filter changes nothing; delete by id answers vouchersDeleted
        $before = $this->vouchers()->json('totalCount');
        $this->assertEnvelope($this->unifi('DELETE', "/v1/sites/{$this->site}/hotspot/vouchers"), 400);
        $this->assertSame($before, $this->vouchers()->json('totalCount'));

        $this->unifi('DELETE', "/v1/sites/{$this->site}/hotspot/vouchers/{$vouchers[0]['id']}")->assertOk()->assertExactJson(['vouchersDeleted' => 1]);
        $this->assertSame($before - 1, $this->vouchers()->json('totalCount'));
    }

    public function test_bulk_delete_with_a_filter(): void
    {
        $response = $this->unifi('DELETE', "/v1/sites/{$this->site}/hotspot/vouchers?filter=".urlencode('expired.eq(true)'));

        $response->assertOk()->assertExactJson(['vouchersDeleted' => 1]);
        $this->assertSame(0, $this->vouchers('?filter='.urlencode('expired.eq(true)'))->json('totalCount'));
    }

    public function test_generate_input_is_validated_and_creates_nothing(): void
    {
        $before = MockResource::query()->where('type', ResourceType::Voucher->value)->count();

        foreach ([['timeLimitMinutes' => 60], ['name' => 'x'], ['name' => 'x', 'timeLimitMinutes' => 0], ['name' => 'x', 'timeLimitMinutes' => 60, 'count' => -1]] as $body) {
            $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$this->site}/hotspot/vouchers", $body), 400);
        }

        $this->assertSame($before, MockResource::query()->where('type', ResourceType::Voucher->value)->count());
    }

    public function test_count_defaults_to_one(): void
    {
        $this->assertCount(1, $this->unifi('POST', "/v1/sites/{$this->site}/hotspot/vouchers", ['name' => 'x', 'timeLimitMinutes' => 30])->json('vouchers'));
    }

    public function test_the_voucher_list_defaults_to_limit_100(): void
    {
        $this->assertSame(100, $this->vouchers()->json('limit'));
    }

    public function test_large_scenario_pages_without_skips_or_repeats(): void
    {
        app(ScenarioRunner::class)->run('large');
        $site = $this->unifi('GET', '/v1/sites')->json('data.0.id');

        $seen = [];
        for ($offset = 0; $offset < 300; $offset += 100) {
            $page = $this->unifi('GET', "/v1/sites/{$site}/hotspot/vouchers?offset={$offset}&limit=100")->json();
            $this->assertSame(100, $page['count']);
            $this->assertSame(300, $page['totalCount']);
            $seen = [...$seen, ...array_column($page['data'], 'id')];
        }

        $last = $this->unifi('GET', "/v1/sites/{$site}/hotspot/vouchers?offset=250&limit=100")->json();
        $this->assertSame(50, $last['count']);
        $this->assertCount(300, $seen);
        $this->assertCount(300, array_unique($seen));
    }

    public function test_a_voucher_of_another_site_is_not_found(): void
    {
        $other = $this->seedSite('Other');
        $voucherId = $this->vouchers()->json('data.0.id');

        $this->assertEnvelope($this->unifi('GET', "/v1/sites/{$other->uuid}/hotspot/vouchers/{$voucherId}"), 404);
        $this->assertEnvelope($this->unifi('GET', '/v1/sites/00000000-0000-4000-8000-000000000000/hotspot/vouchers'), 404);
    }
}

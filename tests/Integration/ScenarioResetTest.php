<?php

namespace Tests\Integration;

use App\Mock\Clock\MockClock;
use App\Mock\Errors\MockError;
use App\Mock\Faults\FaultInjector;
use App\Mock\Scenarios\ScenarioRunner;
use App\Mock\Settings\MockSettings;
use App\Models\MockResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScenarioResetTest extends TestCase
{
    use RefreshDatabase;

    private function snapshot(): array
    {
        return MockResource::query()->orderBy('uuid')->get()
            ->map(fn ($r) => [$r->type, $r->site_uuid, $r->uuid, collect($r->payload)->except(['createdAt', 'activatedAt', 'connectedAt', 'adoptedAt', 'provisionedAt'])->all()])
            ->all();
    }

    public function test_reset_replaces_all_state_and_resets_clock_faults_and_settings(): void
    {
        $runner = app(ScenarioRunner::class);
        $runner->run('default');
        $this->seedSite('Leftover');
        app(MockClock::class)->advanceMinutes(500);
        app(FaultInjector::class)->add(['match' => 'GET *', 'status' => 500, 'times' => 3]);
        app(MockSettings::class)->put(MockSettings::STRICT, true);

        $runner->run('default');

        $this->assertSame(0, MockResource::query()->where('payload->name', 'Leftover')->count());
        $this->assertSame(['mode' => 'real'], app(MockClock::class)->state());
        $this->assertSame([], app(FaultInjector::class)->all());
        $this->assertFalse(app(MockSettings::class)->strict());
    }

    public function test_the_same_scenario_gives_the_same_state_every_time(): void
    {
        $runner = app(ScenarioRunner::class);
        $runner->run('default');
        $first = $this->snapshot();

        $runner->run('default');

        $this->assertSame($first, $this->snapshot(), 'ids and payloads are identical across resets (SC-008)');
    }

    public function test_every_scenario_loads(): void
    {
        $runner = app(ScenarioRunner::class);

        $this->assertSame([], $runner->run('empty'));
        $this->assertSame(2, $runner->run('multi-site')['site']);
        $this->assertSame(300, $runner->run('large')['voucher']);
        $default = $runner->run('default');
        $this->assertSame(1, $default['site']);
        $this->assertSame(5, $default['voucher']);
    }

    public function test_an_unknown_scenario_is_refused(): void
    {
        $this->expectException(MockError::class);
        app(ScenarioRunner::class)->run('nope');
    }

    public function test_the_console_command(): void
    {
        $this->artisan('mock:reset', ['scenario' => 'default'])->assertSuccessful();
        $this->artisan('mock:reset', ['scenario' => 'nope'])->assertFailed();
    }

    public function test_no_scenario_contains_a_real_address(): void
    {
        foreach (array_keys(ScenarioRunner::SCENARIOS) as $name) {
            app(ScenarioRunner::class)->run($name);
            foreach (MockResource::all() as $r) {
                $json = json_encode($r->payload);
                preg_match_all('/"((?:\d{1,3}\.){3}\d{1,3})"/', $json, $ips);
                foreach ($ips[1] as $ip) {
                    $this->assertMatchesRegularExpression('/^(192\.0\.2|198\.51\.100|203\.0\.113)\./', $ip, "{$name}: {$ip} is not an RFC 5737 address");
                }
                preg_match_all('/"(([0-9a-f]{2}:){5}[0-9a-f]{2})"/i', $json, $macs);
                foreach ($macs[1] as $mac) {
                    $this->assertSame(2, hexdec(substr($mac, 0, 2)) & 0x02, "{$name}: {$mac} is not locally administered");
                }
            }
        }
    }
}

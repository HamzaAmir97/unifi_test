<?php

namespace Tests\Feature\Integration;

use App\Mock\Manifest\Manifest;
use App\Mock\Scenarios\ScenarioRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WifiBroadcastsTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScenarioRunner::class)->run('default');
        $this->site = $this->unifi('GET', '/v1/sites')->json('data.0.id');
    }

    private function body(array $over = []): array
    {
        return array_replace([
            'name' => 'Pop-up', 'type' => 'STANDARD', 'enabled' => true, 'channel2gLockedTo6' => false, 'clientIsolationEnabled' => false,
            'dtimPeriod2gLockedTo3' => false, 'hideName' => false, 'multicastToUnicastConversionEnabled' => true, 'uapsdEnabled' => true,
            'securityConfiguration' => ['type' => 'WPA2_PERSONAL'],
        ], $over);
    }

    public function test_every_declared_filter_property_is_accepted(): void
    {
        $samples = ['STRING' => "'x'", 'UUID' => "'00000000-0000-4000-8000-000000000000'", 'BOOLEAN' => 'true'];
        foreach (app(Manifest::class)->filterTable('list-wifi-broadcasts') as $name => $p) {
            $fn = $p['functions'][0];
            $arg = str_starts_with($p['type'], 'SET(') ? ($p['type'] === 'SET(DECIMAL)' ? '2.4' : "'00000000-0000-4000-8000-000000000000'") : $samples[$p['type']];
            $this->unifi('GET', "/v1/sites/{$this->site}/wifi/broadcasts?filter=".urlencode("{$name}.{$fn}({$arg})"))->assertOk();
        }
    }

    public function test_create_with_a_network_get_replace_delete(): void
    {
        $guest = $this->unifi('GET', "/v1/sites/{$this->site}/networks?filter=".urlencode("name.eq('Guest')"))->json('data.0.id');

        $created = $this->unifi('POST', "/v1/sites/{$this->site}/wifi/broadcasts", $this->body(['network' => ['type' => 'SPECIFIC', 'networkId' => $guest]]))
            ->assertCreated()->json();
        $this->assertSame($guest, $created['network']['networkId']);
        foreach (app(Manifest::class)->responseFields('get-wifi-broadcast-details')['required'] as $key) {
            $this->assertArrayHasKey($key, $created);
        }

        $this->unifi('PUT', "/v1/sites/{$this->site}/wifi/broadcasts/{$created['id']}", $this->body(['name' => 'Renamed']))->assertOk()->assertJsonPath('name', 'Renamed');
        $this->unifi('DELETE', "/v1/sites/{$this->site}/wifi/broadcasts/{$created['id']}")->assertOk();
        $this->assertEnvelope($this->unifi('GET', "/v1/sites/{$this->site}/wifi/broadcasts/{$created['id']}"), 404);
    }

    public function test_an_unknown_network_is_refused(): void
    {
        $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$this->site}/wifi/broadcasts", $this->body(['network' => ['type' => 'SPECIFIC', 'networkId' => '00000000-0000-4000-8000-000000000000']])), 400);
    }

    public function test_a_missing_required_field_is_refused(): void
    {
        $body = $this->body();
        unset($body['securityConfiguration']);

        $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$this->site}/wifi/broadcasts", $body), 400);
    }
}

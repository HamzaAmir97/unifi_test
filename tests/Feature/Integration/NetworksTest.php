<?php

namespace Tests\Feature\Integration;

use App\Mock\Scenarios\ScenarioRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworksTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScenarioRunner::class)->run('default');
        $this->site = $this->unifi('GET', '/v1/sites')->json('data.0.id');
    }

    private function networkId(string $name): string
    {
        return $this->unifi('GET', "/v1/sites/{$this->site}/networks?filter=".urlencode("name.eq('{$name}')"))->json('data.0.id');
    }

    public function test_list_returns_the_summary_object(): void
    {
        $item = $this->unifi('GET', "/v1/sites/{$this->site}/networks")->assertOk()->json('data.0');

        $this->assertSame(['default', 'enabled', 'id', 'management', 'metadata', 'name', 'vlanId'], array_keys($item));
    }

    public function test_create_get_replace(): void
    {
        $created = $this->unifi('POST', "/v1/sites/{$this->site}/networks", ['name' => 'IoT', 'enabled' => true, 'management' => 'GATEWAY', 'vlanId' => 40])
            ->assertCreated()->json();
        $this->assertFalse($created['default']);
        $this->assertSame(['origin' => 'USER_DEFINED'], $created['metadata']);

        $this->unifi('GET', "/v1/sites/{$this->site}/networks/{$created['id']}")->assertOk()->assertJsonPath('vlanId', 40);

        $replaced = $this->unifi('PUT', "/v1/sites/{$this->site}/networks/{$created['id']}", ['name' => 'IoT-2', 'enabled' => false, 'management' => 'GATEWAY', 'vlanId' => 41])
            ->assertOk()->json();
        $this->assertSame(['IoT-2', false, 41], [$replaced['name'], $replaced['enabled'], $replaced['vlanId']]);
    }

    public function test_required_fields_and_duplicate_vlan_are_refused(): void
    {
        $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$this->site}/networks", ['name' => 'x', 'enabled' => true, 'management' => 'GATEWAY']), 400);
        $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$this->site}/networks", ['name' => 'x', 'enabled' => true, 'management' => 'GATEWAY', 'vlanId' => 20]), 400);
    }

    public function test_a_referenced_network_is_not_deleted_without_force(): void
    {
        $guest = $this->networkId('Guest');

        $this->assertEnvelope($this->unifi('DELETE', "/v1/sites/{$this->site}/networks/{$guest}?force=false"), 409);
        $this->unifi('GET', "/v1/sites/{$this->site}/networks/{$guest}")->assertOk();

        $deleted = $this->unifi('DELETE', "/v1/sites/{$this->site}/networks/{$guest}?force=true");
        $deleted->assertOk();
        $this->assertSame('', $deleted->getContent());
        $this->assertStringStartsWith('text/plain', $deleted->headers->get('Content-Type'));
        $this->assertEnvelope($this->unifi('GET', "/v1/sites/{$this->site}/networks/{$guest}"), 404);
    }

    public function test_the_default_network_is_never_deleted(): void
    {
        $this->assertEnvelope($this->unifi('DELETE', "/v1/sites/{$this->site}/networks/{$this->networkId('Default')}?force=true"), 409);
    }

    public function test_force_must_be_boolean(): void
    {
        $this->assertEnvelope($this->unifi('DELETE', "/v1/sites/{$this->site}/networks/{$this->networkId('Members')}?force=maybe"), 400);
    }

    public function test_references_are_derived(): void
    {
        $refs = $this->unifi('GET', "/v1/sites/{$this->site}/networks/{$this->networkId('Guest')}/references")->assertOk()->json('referenceResources');
        $byType = array_column($refs, null, 'resourceType');

        $this->assertSame(1, $byType['WIFI_BROADCAST']['referenceCount']);
        $this->assertSame(1, $byType['CLIENT']['referenceCount']);
    }
}

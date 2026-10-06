<?php

namespace Tests\Feature\Integration;

use App\Mock\Scenarios\ScenarioRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientsTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScenarioRunner::class)->run('default');
        $this->site = $this->unifi('GET', '/v1/sites')->json('data.0.id');
    }

    public function test_list_and_get(): void
    {
        $list = $this->unifi('GET', "/v1/sites/{$this->site}/clients")->assertOk()->json();
        $this->assertSame(6, $list['totalCount']);

        $client = $this->unifi('GET', "/v1/sites/{$this->site}/clients/{$list['data'][0]['id']}")->assertOk()->json();
        $this->assertSame(['access', 'id', 'name', 'type', 'connectedAt', 'ipAddress'], array_keys($client));
        $this->assertSame(['type', 'authorized'], array_keys($client['access']));
    }

    public function test_mac_address_is_filterable_without_being_returned(): void
    {
        $all = $this->unifi('GET', "/v1/sites/{$this->site}/clients?filter=".urlencode('macAddress.isNotNull()'))->assertOk()->json();

        $this->assertSame(6, $all['totalCount']);
        $this->assertArrayNotHasKey('macAddress', $all['data'][0]);
    }

    public function test_an_action_answers_its_name_and_changes_authorization(): void
    {
        $visitor = $this->unifi('GET', "/v1/sites/{$this->site}/clients?filter=".urlencode("access.type.eq('GUEST')"))->json('data.0.id');

        $this->unifi('POST', "/v1/sites/{$this->site}/clients/{$visitor}/actions", ['action' => 'AUTHORIZE_GUEST_ACCESS'])
            ->assertOk()->assertExactJson(['action' => 'AUTHORIZE_GUEST_ACCESS']);
        $this->assertTrue($this->unifi('GET', "/v1/sites/{$this->site}/clients/{$visitor}")->json('access.authorized'));

        $this->unifi('POST', "/v1/sites/{$this->site}/clients/{$visitor}/actions", ['action' => 'UNAUTHORIZE_GUEST_ACCESS'])->assertOk();
        $this->assertFalse($this->unifi('GET', "/v1/sites/{$this->site}/clients/{$visitor}")->json('access.authorized'));
    }

    public function test_an_action_needs_a_body(): void
    {
        $id = $this->unifi('GET', "/v1/sites/{$this->site}/clients")->json('data.0.id');

        $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$this->site}/clients/{$id}/actions", []), 400);
    }
}

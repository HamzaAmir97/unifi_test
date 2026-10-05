<?php

namespace Tests\Feature\Integration;

use App\Models\MockRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US1 scenarios 2, 5, 6. */
class SitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_local_sites(): void
    {
        $site = $this->seedSite('Default');

        $this->unifi('GET', '/v1/sites')
            ->assertOk()
            ->assertExactJson([
                'offset' => 0, 'limit' => 25, 'count' => 1, 'totalCount' => 1,
                'data' => [['id' => $site->uuid, 'internalReference' => 'default', 'name' => 'Default']],
            ]);
    }

    public function test_a_filter_is_honoured_although_the_collection_ships_it_disabled(): void
    {
        $this->seedSite('Default');
        $this->seedSite('Branch');

        $response = $this->unifi('GET', '/v1/sites?filter='.urlencode("name.eq('Branch')"));

        $response->assertOk()->assertJsonPath('totalCount', 1)->assertJsonPath('data.0.name', 'Branch');
    }

    public function test_a_malformed_filter_is_400_in_the_envelope(): void
    {
        $this->seedSite();

        $this->assertEnvelope($this->unifi('GET', '/v1/sites?filter='.urlencode('name.eq(')), 400);
    }

    public function test_an_undeclared_filter_property_is_400(): void
    {
        $this->assertEnvelope($this->unifi('GET', '/v1/sites?filter='.urlencode("description.eq('x')")), 400);
    }

    public function test_an_unknown_path_is_404_and_logged_as_not_in_collection(): void
    {
        $this->assertEnvelope($this->unifi('GET', '/v1/nope'), 404);

        $this->assertSame('not_in_collection', MockRequest::query()->latest('id')->first()->warnings[0]['type']);
    }

    public function test_pagination_input_is_validated(): void
    {
        $this->assertEnvelope($this->unifi('GET', '/v1/sites?limit=abc'), 400);
        $this->assertEnvelope($this->unifi('GET', '/v1/sites?offset=-1'), 400);
    }
}

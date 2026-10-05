<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_post_without_a_session_never_answers_419(): void
    {
        $site = $this->seedSite();

        // A raw POST, as Postman sends it: no session, no CSRF token, no Accept header.
        $response = $this->call('POST', self::BASE."/v1/sites/{$site->uuid}/hotspot/vouchers", [], [], [],
            ['HTTP_X_API_KEY' => self::KEY, 'CONTENT_TYPE' => 'application/json'], '{"name":"x","timeLimitMinutes":60}');

        $this->assertNotSame(419, $response->getStatusCode());
    }

    public function test_the_root_describes_the_mock(): void
    {
        $this->getJson('/')->assertOk()->assertJsonStructure(['name', 'version', 'integrationBase', 'controlBase', 'swagger']);
    }

    public function test_there_is_no_api_prefix(): void
    {
        $this->assertEnvelope($this->getJson('/api'.self::BASE.'/v1/info', ['X-API-KEY' => self::KEY]), 404);
    }
}

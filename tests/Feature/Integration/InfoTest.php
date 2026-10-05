<?php

namespace Tests\Feature\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US1 scenarios 1, 3, 4. */
class InfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_info(): void
    {
        $this->unifi('GET', '/v1/info')->assertOk()->assertExactJson(['applicationVersion' => config('unifi_mock.app_version')]);
    }

    public function test_without_a_key_it_is_401(): void
    {
        $this->assertEnvelope($this->getJson(self::BASE.'/v1/info'), 401);
    }

    public function test_with_a_wrong_key_it_is_401(): void
    {
        $this->assertEnvelope($this->getJson(self::BASE.'/v1/info', ['X-API-KEY' => 'wrong']), 401);
    }

    public function test_with_a_wrong_console_it_is_refused(): void
    {
        $this->assertEnvelope($this->getJson('/v1/connector/consoles/other/proxy/network/integration/v1/info', ['X-API-KEY' => self::KEY]), 404);
    }
}

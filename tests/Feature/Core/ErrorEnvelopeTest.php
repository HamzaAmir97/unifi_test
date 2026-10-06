<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** R5 / FR-010 / SC-005: one envelope for every error class, whatever the Accept header. */
class ErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_path_with_and_without_accept_json(): void
    {
        $this->assertEnvelope($this->getJson(self::BASE.'/v1/nope', ['X-API-KEY' => self::KEY]), 404);

        $html = $this->get(self::BASE.'/v1/nope', ['X-API-KEY' => self::KEY]);
        $this->assertEnvelope($html, 404);
        $this->assertStringStartsWith('application/json', $html->headers->get('Content-Type'));
    }

    public function test_wrong_method(): void
    {
        $this->assertEnvelope($this->unifi('DELETE', '/v1/info'), 405);
    }

    public function test_validation_failure_is_400_never_422(): void
    {
        $site = $this->seedSite();

        $this->assertEnvelope($this->unifi('POST', "/v1/sites/{$site->uuid}/hotspot/vouchers", ['name' => 'no time limit']), 400);
    }

    public function test_unexpected_failure_is_500_without_a_trace(): void
    {
        Route::get('__test/boom', fn () => throw new \RuntimeException('secret detail'))->middleware('api');

        $response = $this->get('/__test/boom');

        $this->assertEnvelope($response, 500);
        $this->assertStringNotContainsString('secret detail', $response->getContent());
        $this->assertStringNotContainsString('trace', $response->getContent());
    }

    public function test_envelope_fields(): void
    {
        $body = $this->getJson(self::BASE.'/v1/nope')->json();

        $this->assertSame('NOT_FOUND', $body['statusName']);
        $this->assertSame('/v1/connector/consoles/mock-console/proxy/network/integration/v1/nope', $body['requestPath']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/', $body['timestamp']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $body['requestId']);
    }
}

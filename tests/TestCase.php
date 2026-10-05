<?php

namespace Tests;

use App\Enums\ResourceType;
use App\Mock\Store\ResourceStore;
use App\Models\MockResource;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected const KEY = 'test-key-0123456789';

    protected const BASE = '/v1/connector/consoles/mock-console/proxy/network/integration';

    /**
     * An integration request with the right key and console.
     * (Never name a helper get()/call(): those are public TestCase methods.)
     */
    protected function unifi(string $method, string $path, array $body = [], array $headers = []): TestResponse
    {
        return $this->json($method, self::BASE.$path, $body, ['X-API-KEY' => self::KEY] + $headers);
    }

    protected function control(string $method, string $path, array $body = []): TestResponse
    {
        return $this->json($method, '/__mock'.$path, $body);
    }

    protected function seedSite(string $name = 'Default', ?string $uuid = null): MockResource
    {
        return app(ResourceStore::class)->create(ResourceType::Site, null, ['internalReference' => strtolower($name), 'name' => $name], $uuid);
    }

    protected function assertEnvelope(TestResponse $response, int $status): void
    {
        $response->assertStatus($status);
        $this->assertSame(
            ['statusCode', 'statusName', 'code', 'message', 'timestamp', 'requestPath', 'requestId'],
            array_keys($response->json()),
            'Not the error envelope: '.$response->getContent(),
        );
        $this->assertSame($status, $response->json('statusCode'));
    }
}

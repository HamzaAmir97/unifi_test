<?php

namespace Tests\Feature;

use App\Mock\Manifest\Manifest;
use Tests\TestCase;

/** Owner request 2026-10-06: every request can be tried from Swagger UI. */
class SwaggerTest extends TestCase
{
    public function test_the_swagger_ui_page(): void
    {
        $this->get('/docs')->assertOk()->assertSee('swagger-ui', false)->assertSee('openapi.json', false);
    }

    public function test_the_document_has_one_operation_per_manifest_entry(): void
    {
        $doc = $this->getJson('/openapi.json')->assertOk()->json();

        $integration = 0;
        foreach ($doc['paths'] as $path => $item) {
            if (! str_starts_with($path, '/__mock')) {
                $integration += count(array_diff(array_keys($item), ['servers']));
            }
        }

        $this->assertSame('3.0.3', $doc['openapi']);
        $this->assertSame(count(app(Manifest::class)->entries()), $integration);
        $this->assertSame(['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-KEY'], $doc['components']['securitySchemes']['ApiKey']);
        $this->assertSame('mock-console', $doc['servers'][0]['variables']['consoleId']['default']);
    }

    public function test_operations_carry_the_collection_facts(): void
    {
        $doc = $this->getJson('/openapi.json')->json();
        $generate = $doc['paths']['/v1/sites/{siteId}/hotspot/vouchers']['post'];

        $this->assertSame('generate-vouchers', $generate['operationId']);
        $this->assertArrayHasKey('201', $generate['responses']);
        $this->assertSame(['name', 'timeLimitMinutes'], $generate['requestBody']['content']['application/json']['schema']['required']);
        $this->assertStringContainsString('Filterable properties', $doc['paths']['/v1/sites/{siteId}/hotspot/vouchers']['get']['description']);
    }

    public function test_the_control_plane_is_documented_without_security(): void
    {
        $doc = $this->getJson('/openapi.json')->json();
        $control = array_filter($doc['paths'], fn ($k) => str_starts_with($k, '/__mock'), ARRAY_FILTER_USE_KEY);

        $operations = 0;
        foreach ($control as $item) {
            foreach (array_diff_key($item, ['servers' => true]) as $op) {
                $operations++;
                $this->assertSame([], $op['security']);
                $this->assertSame(['Mock control plane'], $op['tags']);
            }
        }
        $this->assertSame(13, $operations);
    }

    public function test_the_committed_control_collection_is_generated_from_the_same_list(): void
    {
        $committed = file_get_contents(base_path('postman/UniFi-Mock-Control.postman_collection.json'));
        $this->artisan('mock:build-control-collection')->assertSuccessful();

        $this->assertSame($committed, file_get_contents(base_path('postman/UniFi-Mock-Control.postman_collection.json')));
    }
}

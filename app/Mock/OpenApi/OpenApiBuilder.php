<?php

namespace App\Mock\OpenApi;

use App\Mock\Manifest\Manifest;
use App\Mock\Unverified;

/**
 * An OpenAPI 3.0 document generated from the manifest — never hand-typed, so Swagger can
 * never describe a request the mock does not route (owner request, 2026-10-06).
 *
 * Integration operations use the connector base as their server and the X-API-KEY scheme.
 * The /__mock control plane (ControlPlaneSpec) is added under its own tag, with no security.
 */
final class OpenApiBuilder
{
    public function __construct(private readonly Manifest $manifest) {}

    public function build(): array
    {
        $paths = [];
        foreach ($this->manifest->entries() as $entry) {
            $paths[$entry['path']][strtolower($entry['method'])] = $this->operation($entry);
        }

        foreach (ControlPlaneSpec::paths() as $path => $operations) {
            $paths['/__mock'.$path] = ['servers' => [['url' => '/']]] + $operations;
        }

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'UniFi Network API — local mock',
                'version' => (string) config('unifi_mock.app_version'),
                'description' => "Generated from the sanitized UniFi Network API Postman collection (resources/manifest.json).\n\n"
                    .'Click **Authorize** and enter the `UNIFI_MOCK_API_KEY` value from `.env` (default `local-dev-key`). '
                    ."Call **List Local Sites** first and use its `id` as `siteId`.\n\n"
                    .'Error responses use the mock\'s envelope, which is UNVERIFIED (G1). The control plane (`/__mock`) is not part of UniFi.',
            ],
            'servers' => [[
                'url' => '/v1/connector/consoles/{consoleId}/proxy/network/integration',
                'variables' => ['consoleId' => ['default' => (string) config('unifi_mock.console_id')]],
            ]],
            'security' => [['ApiKey' => []]],
            'tags' => [...array_map(fn ($g) => ['name' => $g], array_values(array_unique(array_column($this->manifest->entries(), 'group')))), ['name' => ControlPlaneSpec::TAG, 'description' => 'Mock-only control plane: scenarios, clock, voucher redeem, faults, request log.']],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => ['ApiKey' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-KEY']],
                'schemas' => ['Error' => $this->errorSchema()],
                'responses' => ['Error' => ['description' => 'Error envelope (UNVERIFIED, G1)', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]]],
            ],
        ];
    }

    private function operation(array $entry): array
    {
        $parameters = [];
        foreach ($entry['pathParams'] as $name) {
            $parameters[] = [
                'name' => $name, 'in' => 'path', 'required' => true,
                'schema' => $name === 'portIdx' ? ['type' => 'integer'] : ['type' => 'string', 'format' => 'uuid'],
            ];
        }
        foreach ($entry['query'] as $q) {
            $parameters[] = ['name' => $q['key'], 'in' => 'query', 'required' => $q['required'], 'schema' => $this->querySchema($q)];
        }

        $operation = [
            'tags' => [$entry['group']],
            'summary' => $entry['name'],
            'operationId' => $entry['id'],
            'description' => $this->description($entry),
            'parameters' => $parameters,
            'responses' => [
                (string) $entry['status'] => [
                    'description' => 'As documented by the collection',
                    'content' => [$entry['contentType'] => $entry['contentType'] === 'text/plain' ? ['schema' => ['type' => 'string', 'maxLength' => 0]] : new \stdClass],
                ],
                'default' => ['$ref' => '#/components/responses/Error'],
            ],
        ];

        if ($entry['request'] !== null) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => ['application/json' => [
                    'schema' => ['type' => 'object', 'required' => $entry['request']['fields']['']['required'] ?? []],
                    'example' => $this->example($entry['request']['example']),
                ]],
            ];
        }

        return $operation;
    }

    private function querySchema(array $q): array
    {
        return match ($q['key']) {
            'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => (int) $q['default']],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => Unverified::MAX_LIMIT, 'default' => (int) $q['default']],
            'force' => ['type' => 'boolean', 'default' => false],
            'filter' => ['type' => 'string'],
            default => ['type' => 'string', 'format' => 'uuid'],
        };
    }

    private function description(array $entry): string
    {
        $text = $entry['description'];
        $properties = $entry['filter']['properties'];
        if ($properties !== []) {
            $text .= "\n\n**Filterable properties** (syntax UNVERIFIED, G2 — e.g. `name.eq('Guest')`, `and(enabled.eq(true), name.like('Gu*'))`):\n\n|Name|Type|Functions|\n|-|-|-|\n";
            foreach ($properties as $name => $p) {
                $text .= "|`{$name}`|`{$p['type']}`|".implode(' ', $p['functions'])."|\n";
            }
        }
        if ($entry['flags']['truncatedExample']) {
            $text .= "\n\n_The collection's example for this request is truncated; nested values are hand-authored (UNVERIFIED)._";
        }

        return trim($text);
    }

    /** The collection's placeholders ("<string>", "<long>" …) become sample values Swagger can send. */
    private function example(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->example($v), $value);
        }
        if (! is_string($value) || ! preg_match('/^<([^>]+)>$/', $value, $m)) {
            return $value;
        }

        return match ($m[1]) {
            'boolean' => true,
            'integer', 'long' => 60,
            'number', 'double' => 1.5,
            'uuid' => '00000000-0000-4000-8000-000000000000',
            'string' => 'example',
            default => null,
        };
    }

    private function errorSchema(): array
    {
        return [
            'type' => 'object',
            'required' => Unverified::ERROR_ENVELOPE_FIELDS,
            'properties' => [
                'statusCode' => ['type' => 'integer', 'example' => 400],
                'statusName' => ['type' => 'string', 'example' => 'BAD_REQUEST'],
                'code' => ['type' => 'string', 'example' => Unverified::ERROR_CODES['request_invalid']],
                'message' => ['type' => 'string'],
                'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                'requestPath' => ['type' => 'string'],
                'requestId' => ['type' => 'string', 'format' => 'uuid'],
            ],
        ];
    }
}

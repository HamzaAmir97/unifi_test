<?php

namespace App\Mock\OpenApi;

/**
 * OpenAPI operations of the /__mock control plane (contracts/control-api.md), so Swagger
 * can drive scenarios, the clock, redeem and faults too. No X-API-KEY (security: []).
 */
final class ControlPlaneSpec
{
    public const TAG = 'Mock control plane';

    /** @return array<string, array<string, mixed>> path (after /__mock) => operations */
    public static function paths(): array
    {
        return [
            '/hotspot/redeem' => ['post' => self::op('mock-redeem', 'Redeem a voucher (guest at the captive portal)',
                'Activates the voucher on first use, counts the guest and creates an authorized guest client. Unknown code 404; expired or full 409.',
                ['siteId' => '<site id from List Local Sites>', 'code' => '4815162342', 'name' => 'Visitor'], 200)],
            '/clock' => ['post' => self::op('mock-clock', 'Move the mock clock',
                'Exactly one of `advanceMinutes`, `now` (ISO-8601) or `reset: true`.',
                ['advanceMinutes' => 61], 200)],
        ];
    }

    private static function op(string $id, string $summary, string $description, ?array $example, int $status, array $parameters = []): array
    {
        $op = [
            'tags' => [self::TAG],
            'operationId' => $id,
            'summary' => $summary,
            'description' => $description,
            'security' => [],
            'parameters' => $parameters,
            'responses' => [
                (string) $status => ['description' => 'OK', 'content' => ['application/json' => new \stdClass]],
                'default' => ['$ref' => '#/components/responses/Error'],
            ],
        ];
        if ($example !== null) {
            $op['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object'], 'example' => $example]]];
        }

        return $op;
    }
}

<?php

namespace App\Mock\OpenApi;

/**
 * The /__mock control plane described once (contracts/control-api.md). Both the Swagger
 * document and the separate Postman control collection (`mock:build-control-collection`)
 * are generated from this list, so they cannot disagree. No X-API-KEY (security: []).
 */
final class ControlPlaneSpec
{
    public const TAG = 'Mock control plane';

    /** @return list<array{id: string, method: string, path: string, summary: string, description: string, query?: array<string, string>, body?: array<string, mixed>, status: int}> */
    public static function operations(): array
    {
        return [
            ['id' => 'mock-reset', 'method' => 'POST', 'path' => '/reset', 'query' => ['seed' => 'default'], 'status' => 200,
                'summary' => 'Reset to a scenario', 'description' => 'Replaces all state with `default`, `empty`, `multi-site` or `large`, resets the clock to real time, clears faults and restores default settings.'],
            ['id' => 'mock-state', 'method' => 'GET', 'path' => '/state', 'status' => 200,
                'summary' => 'Read the full state', 'description' => 'Clock, strict mode, optional-field mode, faults and every stored resource.'],
            ['id' => 'mock-redeem', 'method' => 'POST', 'path' => '/hotspot/redeem', 'status' => 200,
                'body' => ['siteId' => '{{siteId}}', 'code' => '4815162342', 'name' => 'Visitor'],
                'summary' => 'Redeem a voucher (guest at the captive portal)', 'description' => 'Activates the voucher on first use, counts the guest and creates an authorized guest client. Unknown code 404; expired or full 409.'],
            ['id' => 'mock-clients-connect', 'method' => 'POST', 'path' => '/clients/connect', 'status' => 201,
                'body' => ['siteId' => '{{siteId}}', 'name' => 'Walk-in laptop', 'type' => 'WIRELESS'],
                'summary' => 'Connect a client', 'description' => '`type` is WIRED, WIRELESS or VPN; `macAddress` and `ipAddress` are optional.'],
            ['id' => 'mock-clients-disconnect', 'method' => 'POST', 'path' => '/clients/disconnect', 'status' => 200,
                'body' => ['siteId' => '{{siteId}}', 'clientId' => '{{clientId}}'],
                'summary' => 'Disconnect a client', 'description' => 'Removes the client. Unknown client 404.'],
            ['id' => 'mock-devices-pending', 'method' => 'POST', 'path' => '/devices/pending', 'status' => 201,
                'body' => ['macAddress' => '02:a7:00:00:10:01', 'model' => 'U7-Lite'],
                'summary' => 'Add a device pending adoption', 'description' => 'The device appears in List Devices Pending Adoption and can be adopted. Duplicate MAC 409.'],
            ['id' => 'mock-clock', 'method' => 'POST', 'path' => '/clock', 'status' => 200,
                'body' => ['advanceMinutes' => 61],
                'summary' => 'Move the mock clock', 'description' => 'Exactly one of `advanceMinutes`, `now` (ISO-8601) or `reset: true`.'],
            ['id' => 'mock-faults', 'method' => 'GET', 'path' => '/faults', 'status' => 200,
                'summary' => 'List fault rules', 'description' => 'Pending injected failures.'],
            ['id' => 'mock-faults-add', 'method' => 'POST', 'path' => '/faults', 'status' => 201,
                'body' => ['match' => 'GET */hotspot/vouchers', 'status' => 429, 'times' => 2],
                'summary' => 'Inject a fault', 'description' => '`match` is "METHOD path-glob" on the path after the connector base (`*` matches anything). `status` 400–599, `times` ≥ 1, optional `delayMs` (capped) and `timeout`. Integration routes only.'],
            ['id' => 'mock-faults-clear', 'method' => 'DELETE', 'path' => '/faults', 'status' => 200,
                'summary' => 'Clear all fault rules', 'description' => 'Answers the number cleared.'],
            ['id' => 'mock-requests', 'method' => 'GET', 'path' => '/requests', 'query' => ['limit' => '50'], 'status' => 200,
                'summary' => 'Read the request log', 'description' => 'Newest first; the API key is masked; warnings show unknown enum values, paths not in the collection and injected faults.'],
            ['id' => 'mock-requests-clear', 'method' => 'DELETE', 'path' => '/requests', 'status' => 200,
                'summary' => 'Clear the request log', 'description' => 'Answers the number cleared.'],
            ['id' => 'mock-settings', 'method' => 'POST', 'path' => '/settings', 'status' => 200,
                'body' => ['strict' => false, 'optionalFields' => 'omit'],
                'summary' => 'Change settings', 'description' => '`strict` (D2) refuses unknown type/action values instead of warning; `optionalFields` (G11) is `omit` or `null`.'],
        ];
    }

    /** @return array<string, array<string, mixed>> OpenAPI path items keyed by path (after /__mock) */
    public static function paths(): array
    {
        $paths = [];
        foreach (self::operations() as $op) {
            $operation = [
                'tags' => [self::TAG],
                'operationId' => $op['id'],
                'summary' => $op['summary'],
                'description' => $op['description'],
                'security' => [],
                'parameters' => array_map(fn ($k, $v) => ['name' => $k, 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'default' => $v]], array_keys($op['query'] ?? []), $op['query'] ?? []),
                'responses' => [
                    (string) $op['status'] => ['description' => 'OK', 'content' => ['application/json' => new \stdClass]],
                    'default' => ['$ref' => '#/components/responses/Error'],
                ],
            ];
            if (isset($op['body'])) {
                $example = array_map(fn ($v) => is_string($v) && str_starts_with($v, '{{') ? '<'.trim($v, '{}').'>' : $v, $op['body']);
                $operation['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object'], 'example' => $example]]];
            }
            $paths[$op['path']][strtolower($op['method'])] = $operation;
        }

        return $paths;
    }
}

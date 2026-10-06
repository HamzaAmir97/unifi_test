<?php

namespace App\Mock\Manifest;

use App\Mock\Postman\PostmanTests;
use Illuminate\Support\Str;

/**
 * Reads the sanitized collection and produces the manifest (R7): one entry per request,
 * with everything the runtime needs, derived from the collection and never re-typed.
 */
final class ManifestBuilder
{
    private const TRUNCATION_MARKER = 'Too many levels of nesting to fake this schema';

    /** Fields whose example values are recorded as observed enum values (FR-026). */
    private const ENUM_FIELDS = ['type', 'action', 'mode', 'ipVersion', 'ipsecFilter', 'connectionStateFilter', 'state', 'resourceType', 'role'];

    /** @var array<string, array<string, true>> */
    private array $observed = [];

    /** @return array{source: string, entries: list<array<string, mixed>>, observedEnums: array<string, list<string>>} */
    public function build(array $collection): array
    {
        $this->observed = [];
        $entries = [];

        foreach ($collection['item'] as $group) {
            if ($group['name'] === PostmanTests::READ_ONLY_FOLDER) {
                continue; // copies of GET requests added for production checks, not part of the contract
            }
            foreach ($group['item'] ?? [] as $item) {
                if (isset($item['request'])) {
                    $entries[] = $this->entry($group['name'], $item);
                }
            }
        }

        $observed = [];
        ksort($this->observed);
        foreach ($this->observed as $field => $values) {
            $list = array_keys($values);
            sort($list);
            $observed[$field] = $list;
        }

        return [
            'source' => 'postman/UniFi-Network-API.postman_collection.json',
            'entries' => $entries,
            'observedEnums' => $observed,
        ];
    }

    private function entry(string $group, array $item): array
    {
        $request = $item['request'];
        $response = $item['response'][0] ?? [];
        $url = $request['url'];

        $segments = array_map(fn ($s) => str_starts_with($s, ':') ? '{'.substr($s, 1).'}' : $s, $url['path']);
        $pathParams = array_values(array_map(fn ($s) => substr($s, 1), array_filter($url['path'], fn ($s) => str_starts_with($s, ':'))));

        $query = [];
        foreach ($url['query'] ?? [] as $q) {
            $query[] = [
                'key' => $q['key'],
                'default' => $q['value'] ?? null,
                'required' => str_contains((string) ($q['description'] ?? ''), '(Required)'),
                'disabled' => (bool) ($q['disabled'] ?? false),
            ];
        }
        $queryKeys = array_column($query, 'key');

        $description = $request['description'] ?? '';
        if (is_array($description)) {
            $description = $description['content'] ?? '';
        }
        $filterTable = $this->filterTable((string) $description);
        $filterParam = collect($query)->firstWhere('key', 'filter');

        $rawBody = $request['body']['raw'] ?? null;
        $requestExample = $rawBody !== null ? json_decode($rawBody, true) : null;
        $headers = collect($response['header'] ?? [])->pluck('value', 'key');
        $responseBody = (string) ($response['body'] ?? '');
        $responseExample = $responseBody !== '' ? json_decode($responseBody, true) : null;

        if (is_array($requestExample)) {
            $this->observe($requestExample);
        }
        if (is_array($responseExample)) {
            $this->observe($responseExample);
        }

        $limitDefault = collect($query)->firstWhere('key', 'limit')['default'] ?? null;

        return [
            'id' => Str::slug($item['name']),
            'group' => $group,
            'name' => $item['name'],
            'method' => strtoupper($request['method']),
            'path' => '/'.implode('/', $segments),
            'pathParams' => $pathParams,
            'query' => $query,
            'isList' => in_array('offset', $queryKeys, true) && in_array('limit', $queryKeys, true),
            'defaultLimit' => $limitDefault !== null ? (int) $limitDefault : null,
            'filter' => [
                'parameter' => $filterParam !== null,
                'required' => (bool) ($filterParam['required'] ?? false),
                'disabled' => (bool) ($filterParam['disabled'] ?? false),
                'properties' => $filterTable,
            ],
            'status' => (int) ($response['code'] ?? 200),
            'contentType' => $headers['Content-Type'] ?? 'text/plain',
            'request' => is_array($requestExample) ? [
                'example' => $requestExample,
                'fields' => $this->fieldSets($requestExample, true),
            ] : null,
            'response' => is_array($responseExample) ? [
                'fields' => $this->fieldSets($responseExample, false),
            ] : null,
            'flags' => [
                'tableWithoutParameter' => $filterTable !== [] && $filterParam === null,
                'truncatedExample' => str_contains((string) $rawBody, self::TRUNCATION_MARKER) || str_contains($responseBody, self::TRUNCATION_MARKER),
            ],
            'description' => trim(preg_replace('/<details>.*<\/details>/s', '', (string) $description)),
        ];
    }

    /** @return array<string, array{type: string, functions: list<string>}> */
    private function filterTable(string $description): array
    {
        $table = [];
        preg_match_all('/^\|`([^`]+)`\|`([^`]+)`\|(.*)\|\s*$/m', $description, $rows, PREG_SET_ORDER);
        foreach ($rows as $row) {
            preg_match_all('/`(\w+)`/', $row[3], $fns);
            $table[$row[1]] = ['type' => $row[2], 'functions' => $fns[1]];
        }

        return $table;
    }

    /**
     * Field sets for every object in an example, keyed by dotted path ('' = root, 'a.b',
     * 'list[]' = the element of an array of objects).
     *
     * @return array<string, array<string, mixed>>
     */
    private function fieldSets(array $example, bool $withTypes, string $path = ''): array
    {
        if (array_is_list($example)) {
            return isset($example[0]) && is_array($example[0]) && ! array_is_list($example[0])
                ? $this->fieldSets($example[0], $withTypes, $path.'[]')
                : [];
        }

        $keys = array_map('strval', array_keys($example));
        $isMap = $keys !== [] && collect($keys)->contains(fn ($k) => ! preg_match('/^[A-Za-z_]\w*$/', $k));
        $set = FieldSets::split($keys) + ['map' => $isMap];
        if ($withTypes) {
            $set['types'] = array_combine($keys, array_map(fn ($v) => $this->typeOf($v), array_values($example)));
        }

        $sets = [$path => $set];
        foreach ($example as $key => $value) {
            if (is_array($value) && $value !== []) {
                $sets += $this->fieldSets($value, $withTypes, ltrim($path.'.'.$key, '.'));
            }
        }

        return $sets;
    }

    private function typeOf(mixed $value): string
    {
        if (is_string($value) && preg_match('/^<(\w+)>$/', $value, $m)) {
            return match ($m[1]) {
                'long', 'integer' => 'integer',
                'double', 'number', 'float' => 'number',
                default => $m[1], // string, boolean, uuid, dateTime
            };
        }

        return match (true) {
            is_string($value) && str_contains($value, self::TRUNCATION_MARKER) => 'any',
            $value === '' || $value === null => 'any',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) => 'array',
            default => 'object',
        };
    }

    private function observe(array $example, ?string $field = null): void
    {
        foreach ($example as $key => $value) {
            $name = is_int($key) ? $field : $key;
            if (is_array($value)) {
                $this->observe($value, $name);
            } elseif (is_string($value) && $name !== null && in_array($name, self::ENUM_FIELDS, true)
                && preg_match('/^[A-Z][A-Z0-9_]+$/', $value)) {
                $this->observed[$name][$value] = true;
            }
        }
    }
}

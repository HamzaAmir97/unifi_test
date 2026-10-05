<?php

namespace App\Mock\Manifest;

/**
 * Turns a manifest request field-set map into Laravel validation rules (FR-012a).
 *
 * - required keys → `required` (or `required_with:parent` when the parent is optional);
 *   arrays use `present` so an empty list is still a value;
 * - optional keys → `sometimes|nullable`;
 * - types come from the collection's placeholders (`<string>`, `<boolean>`, `<uuid>`, `<long>` …);
 * - map-like objects (keys such as "5" and "2.4") and truncated parts are not validated inside;
 * - integers that are limits or counts must be at least 1.
 */
final class ManifestRules
{
    private const POSITIVE_INTEGER = '/(count|limit|minutes|kbps|mbytes)$/i';

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, list<string>>
     */
    public function rules(array $fields): array
    {
        $rules = [];
        foreach ($fields as $path => $set) {
            if (($set['map'] ?? false) || str_contains($path, '[]')) {
                continue;
            }
            $prefix = $path === '' ? '' : $path.'.';
            $parentRequired = $this->chainRequired($fields, $path);

            foreach ($set['required'] as $key) {
                $type = $set['types'][$key] ?? 'any';
                $presence = $type === 'array' ? 'present' : 'required';
                $rules[$prefix.$key] = [$parentRequired ? $presence : "required_with:{$path}", ...$this->typeRules($key, $type)];
            }
            foreach ($set['optional'] as $key) {
                $rules[$prefix.$key] = ['sometimes', 'nullable', ...$this->typeRules($key, $set['types'][$key] ?? 'any')];
            }
        }

        return $rules;
    }

    /** True when every object on the way down to $path is itself a required key. */
    private function chainRequired(array $fields, string $path): bool
    {
        if ($path === '') {
            return true;
        }
        $segments = explode('.', $path);
        $key = array_pop($segments);
        $parent = implode('.', $segments);

        return in_array($key, $fields[$parent]['required'] ?? [], true) && $this->chainRequired($fields, $parent);
    }

    /** @return list<string> */
    private function typeRules(string $key, string $type): array
    {
        return match ($type) {
            'string' => ['string'],
            'boolean' => ['boolean'],
            'integer' => preg_match(self::POSITIVE_INTEGER, $key) ? ['integer', 'min:1'] : ['integer'],
            'number' => ['numeric'],
            'uuid' => ['uuid'],
            'dateTime' => ['date'],
            'array', 'object' => ['array'],
            default => [],
        };
    }
}

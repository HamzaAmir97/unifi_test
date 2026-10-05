<?php

namespace App\Mock\Manifest;

/**
 * Required versus optional fields, read from field ORDER (R2, UNVERIFIED G11).
 *
 * The collection is an OpenAPI import: an object lists its required keys alphabetically,
 * then its optional keys alphabetically. The boundary is the first alphabetical descent.
 * A list that never descends has no detectable boundary and is treated as all-required —
 * strict on requests is the safe direction — and flagged `noDescent` so it can be listed.
 */
final class FieldSets
{
    /**
     * @param  list<string>  $keys  in collection order
     * @return array{required: list<string>, optional: list<string>, noDescent: bool}
     */
    public static function split(array $keys): array
    {
        $keys = array_values(array_map('strval', $keys));
        for ($i = 1, $n = count($keys); $i < $n; $i++) {
            if (strcmp($keys[$i - 1], $keys[$i]) > 0) {
                return [
                    'required' => array_slice($keys, 0, $i),
                    'optional' => array_slice($keys, $i),
                    'noDescent' => false,
                ];
            }
        }

        return ['required' => $keys, 'optional' => [], 'noDescent' => true];
    }
}

<?php

namespace App\Mock\Filter;

/**
 * The property types the collection's filter tables declare: seven scalars, plus SET(x)
 * of a scalar (`SET(UUID)`, `SET(STRING)`, `SET(INTEGER)`, `SET(DECIMAL)`).
 */
enum PropertyType: string
{
    case Uuid = 'UUID';
    case String = 'STRING';
    case Integer = 'INTEGER';
    case Decimal = 'DECIMAL';
    case Boolean = 'BOOLEAN';
    case Timestamp = 'TIMESTAMP';

    /** @return array{0: self, 1: bool} the element type and whether it is a set */
    public static function parse(string $declared): array
    {
        if (preg_match('/^SET\((\w+)\)$/', $declared, $m)) {
            return [self::from($m[1]), true];
        }

        return [self::from($declared), false];
    }

    public function accepts(mixed $value): bool
    {
        return match ($this) {
            self::Uuid => is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1,
            self::String => is_string($value),
            self::Integer => is_int($value),
            self::Decimal => is_int($value) || is_float($value),
            self::Boolean => is_bool($value),
            self::Timestamp => is_string($value) && strtotime($value) !== false,
        };
    }

    /** A comparable form: timestamps become epoch milliseconds, strings compare case-insensitively for uuids. */
    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::Timestamp => is_string($value) ? (int) round((float) (new \DateTimeImmutable($value))->format('U.u') * 1000) : $value,
            self::Uuid => is_string($value) ? strtolower($value) : $value,
            self::Decimal => is_numeric($value) ? (float) $value : $value,
            default => $value,
        };
    }
}

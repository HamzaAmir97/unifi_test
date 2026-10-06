<?php

namespace App\Mock\Filter;

use App\Mock\Errors\MockError;
use App\Mock\Unverified;

/**
 * Evaluates a parsed filter against presented response objects (R6, UNVERIFIED G2).
 *
 * The declared table — property name => [type, functions] — comes from the manifest, so a
 * list honours exactly the properties and functions the collection declares for it.
 * Anything else is a 400 FILTER_INVALID, raised before any row is looked at.
 */
final class FilterEvaluator
{
    private const ARITY = [
        'eq' => [1, 1], 'ne' => [1, 1], 'gt' => [1, 1], 'ge' => [1, 1], 'lt' => [1, 1], 'le' => [1, 1],
        'like' => [1, 1], 'in' => [1, PHP_INT_MAX], 'notIn' => [1, PHP_INT_MAX],
        'isNull' => [0, 0], 'isNotNull' => [0, 0], 'isEmpty' => [0, 0],
        'contains' => [1, 1], 'containsAny' => [1, PHP_INT_MAX], 'containsAll' => [1, PHP_INT_MAX], 'containsExactly' => [1, PHP_INT_MAX],
    ];

    /** @param array<string, array{type: string, functions: list<string>}> $declared */
    public function __construct(private readonly array $declared) {}

    /** Check every call against the declared table before evaluating anything. */
    public function validate(FilterNode $node): void
    {
        if (! $node->isCall()) {
            foreach ($node->children as $child) {
                $this->validate($child);
            }

            return;
        }

        $declared = $this->declared[$node->property] ?? null;
        if ($declared === null) {
            throw MockError::filterInvalid("Property '{$node->property}' is not filterable on this list.");
        }
        if (! in_array($node->function, $declared['functions'], true)) {
            throw MockError::filterInvalid("Function '{$node->function}' is not allowed on '{$node->property}'.");
        }
        [$min, $max] = self::ARITY[$node->function] ?? throw MockError::filterInvalid("Unknown function '{$node->function}'.");
        $n = count($node->args);
        if ($n < $min || $n > $max) {
            throw MockError::filterInvalid("'{$node->function}' on '{$node->property}' takes ".($min === $max ? $min : "{$min} or more").' argument(s).');
        }

        [$type] = PropertyType::parse($declared['type']);
        foreach ($node->args as $arg) {
            if (! $type->accepts($arg)) {
                throw MockError::filterInvalid('Argument '.json_encode($arg)." is not a valid {$type->value} for '{$node->property}'.");
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items  presented objects
     * @return list<array<string, mixed>>
     */
    public function filter(FilterNode $node, array $items): array
    {
        $this->validate($node);

        return array_values(array_filter($items, fn (array $item) => $this->matches($node, $item)));
    }

    /** @param array<string, mixed> $item */
    public function matches(FilterNode $node, array $item): bool
    {
        return match ($node->kind) {
            'and' => array_reduce($node->children, fn ($ok, $c) => $ok && $this->matches($c, $item), true),
            'or' => array_reduce($node->children, fn ($ok, $c) => $ok || $this->matches($c, $item), false),
            'not' => ! $this->matches($node->children[0], $item),
            default => $this->call($node, $item),
        };
    }

    /** @param array<string, mixed> $item */
    private function call(FilterNode $node, array $item): bool
    {
        [$type, $isSet] = PropertyType::parse($this->declared[$node->property]['type']);
        $raw = self::resolve($item, explode('.', $node->property), $isSet);
        $args = array_map(fn ($a) => $type->normalize($a), $node->args);

        if ($node->function === 'isNull') {
            return $raw === null;
        }
        if ($node->function === 'isNotNull') {
            return $raw !== null;
        }

        if ($isSet) {
            $set = array_map(fn ($v) => $type->normalize($v), is_array($raw) ? array_values($raw) : []);

            return match ($node->function) {
                'isEmpty' => $set === [],
                'contains' => in_array($args[0], $set, true),
                'containsAny' => array_intersect($args, $set) !== [],
                'containsAll' => array_diff($args, $set) === [],
                'containsExactly' => $this->sameSet($args, $set),
                'eq' => $this->sameSet($args, $set),
                'ne' => ! $this->sameSet($args, $set),
                default => false,
            };
        }

        $value = $type->normalize($raw);

        return match ($node->function) {
            'eq' => $value === $args[0],
            'ne' => $value !== $args[0],
            'in' => in_array($value, $args, true),
            'notIn' => ! in_array($value, $args, true),
            'gt' => $value !== null && $value > $args[0],
            'ge' => $value !== null && $value >= $args[0],
            'lt' => $value !== null && $value < $args[0],
            'le' => $value !== null && $value <= $args[0],
            'like' => is_string($value) && preg_match($this->likePattern((string) $args[0]), $value) === 1,
            default => false,
        };
    }

    /**
     * A dotted path that may cross lists: `members.deviceId` over a list of members yields
     * every member's deviceId. For a SET property the result is flattened to a list.
     *
     * @param  list<string>  $segments
     */
    private static function resolve(mixed $value, array $segments, bool $isSet): mixed
    {
        foreach ($segments as $i => $segment) {
            if (is_array($value) && array_is_list($value) && $value !== [] && is_array($value[0])) {
                $rest = array_slice($segments, $i);
                $out = [];
                foreach ($value as $element) {
                    $v = self::resolve($element, $rest, $isSet);
                    if ($v === null) {
                        continue;
                    }
                    is_array($v) && array_is_list($v) ? array_push($out, ...$v) : $out[] = $v;
                }

                return $out;
            }
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function sameSet(array $a, array $b): bool
    {
        $a = array_values(array_unique($a, SORT_REGULAR));
        $b = array_values(array_unique($b, SORT_REGULAR));
        sort($a);
        sort($b);

        return $a === $b;
    }

    private function likePattern(string $pattern): string
    {
        $parts = array_map(fn ($p) => preg_quote($p, '/'), explode(Unverified::FILTER_LIKE_WILDCARD, $pattern));

        return '/^'.implode('.*', $parts).'$/su';
    }
}

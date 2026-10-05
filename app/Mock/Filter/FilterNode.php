<?php

namespace App\Mock\Filter;

/**
 * One node of a parsed filter: a combinator (`and`, `or`, `not`) over children, or a
 * call `property.function(args)`.
 */
final readonly class FilterNode
{
    /**
     * @param  list<FilterNode>  $children
     * @param  list<string|int|float|bool|null>  $args
     */
    private function __construct(
        public string $kind,
        public array $children = [],
        public string $property = '',
        public string $function = '',
        public array $args = [],
    ) {}

    /** @param list<FilterNode> $children */
    public static function combinator(string $kind, array $children): self
    {
        return new self($kind, $children);
    }

    /** @param list<string|int|float|bool|null> $args */
    public static function call(string $property, string $function, array $args): self
    {
        return new self('call', property: $property, function: $function, args: $args);
    }

    public function isCall(): bool
    {
        return $this->kind === 'call';
    }
}

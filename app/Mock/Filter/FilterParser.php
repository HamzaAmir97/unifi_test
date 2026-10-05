<?php

namespace App\Mock\Filter;

use App\Mock\Errors\MockError;

/**
 * Recursive-descent parser for the filter grammar (R6, UNVERIFIED G2):
 *
 *   expr     := ('and' | 'or') '(' expr (',' expr)+ ')' | 'not' '(' expr ')' | property '.' function '(' args? ')'
 *   property := ident ('.' ident)*
 *   args     := value (',' value)*
 *   value    := 'string' | number | true | false | null
 *
 * Strings are single-quoted; `\'` escapes a quote and `\\` a backslash.
 * Every syntax error is a 400 FILTER_INVALID.
 */
final class FilterParser
{
    private string $src = '';

    private int $pos = 0;

    public function parse(string $filter): FilterNode
    {
        $this->src = $filter;
        $this->pos = 0;

        if (trim($filter) === '') {
            throw MockError::filterInvalid('The filter is empty.');
        }

        $node = $this->expr();
        $this->skipSpace();
        if ($this->pos < strlen($this->src)) {
            $this->fail('unexpected input');
        }

        return $node;
    }

    private function expr(): FilterNode
    {
        $this->skipSpace();
        $first = $this->ident();

        if (in_array($first, ['and', 'or', 'not'], true) && $this->peek() === '(') {
            return $this->combinator($first);
        }

        $segments = [$first];
        while ($this->peek() === '.') {
            $this->pos++;
            $segments[] = $this->ident();
        }
        if (count($segments) < 2) {
            $this->fail('expected property.function(...)');
        }
        $function = array_pop($segments);
        $this->expect('(');
        $args = [];
        $this->skipSpace();
        if ($this->peek() !== ')') {
            $args[] = $this->value();
            $this->skipSpace();
            while ($this->peek() === ',') {
                $this->pos++;
                $args[] = $this->value();
                $this->skipSpace();
            }
        }
        $this->expect(')');

        return FilterNode::call(implode('.', $segments), $function, $args);
    }

    private function combinator(string $kind): FilterNode
    {
        $this->expect('(');
        $children = [$this->expr()];
        $this->skipSpace();
        while ($this->peek() === ',') {
            $this->pos++;
            $children[] = $this->expr();
            $this->skipSpace();
        }
        $this->expect(')');

        if ($kind === 'not' && count($children) !== 1) {
            $this->fail('not() takes exactly one expression');
        }
        if ($kind !== 'not' && count($children) < 2) {
            $this->fail("{$kind}() takes at least two expressions");
        }

        return FilterNode::combinator($kind, $children);
    }

    private function value(): string|int|float|bool|null
    {
        $this->skipSpace();
        $c = $this->peek();

        if ($c === "'") {
            return $this->string();
        }
        if ($c !== null && preg_match('/[-0-9]/', $c)) {
            if (! preg_match('/\G-?\d+(\.\d+)?/', $this->src, $m, 0, $this->pos)) {
                $this->fail('malformed number');
            }
            $this->pos += strlen($m[0]);

            return isset($m[1]) ? (float) $m[0] : (int) $m[0];
        }

        $word = $this->ident();

        return match ($word) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $this->fail("unexpected word '{$word}' where a value was expected"),
        };
    }

    private function string(): string
    {
        $this->pos++; // opening quote
        $out = '';
        $len = strlen($this->src);
        while ($this->pos < $len) {
            $c = $this->src[$this->pos++];
            if ($c === '\\') {
                if ($this->pos >= $len) {
                    break;
                }
                $out .= $this->src[$this->pos++];

                continue;
            }
            if ($c === "'") {
                return $out;
            }
            $out .= $c;
        }
        $this->fail('unterminated string');
    }

    private function ident(): string
    {
        $this->skipSpace();
        if (! preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $this->src, $m, 0, $this->pos)) {
            $this->fail('expected a name');
        }
        $this->pos += strlen($m[0]);

        return $m[0];
    }

    private function expect(string $char): void
    {
        $this->skipSpace();
        if ($this->peek() !== $char) {
            $this->fail("expected '{$char}'");
        }
        $this->pos++;
    }

    private function peek(): ?string
    {
        return $this->src[$this->pos] ?? null;
    }

    private function skipSpace(): void
    {
        while (isset($this->src[$this->pos]) && ctype_space($this->src[$this->pos])) {
            $this->pos++;
        }
    }

    private function fail(string $why): never
    {
        throw MockError::filterInvalid("Invalid filter at position {$this->pos}: {$why}.");
    }
}

<?php

namespace Tests\Unit\Filter;

use App\Mock\Errors\MockError;
use App\Mock\Filter\FilterNode;
use App\Mock\Filter\FilterParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FilterParserTest extends TestCase
{
    private function parse(string $filter): FilterNode
    {
        return (new FilterParser)->parse($filter);
    }

    public function test_a_simple_call(): void
    {
        $node = $this->parse("name.eq('Guest')");

        $this->assertTrue($node->isCall());
        $this->assertSame('name', $node->property);
        $this->assertSame('eq', $node->function);
        $this->assertSame(['Guest'], $node->args);
    }

    public function test_dotted_properties(): void
    {
        $node = $this->parse("hotspotConfiguration.type.eq('CAPTIVE')");

        $this->assertSame('hotspotConfiguration.type', $node->property);
        $this->assertSame('eq', $node->function);
    }

    public function test_scalar_values(): void
    {
        $this->assertSame([42], $this->parse('vlanId.eq(42)')->args);
        $this->assertSame([-1.5], $this->parse('x.gt(-1.5)')->args);
        $this->assertSame([true], $this->parse('enabled.eq(true)')->args);
        $this->assertSame([false], $this->parse('enabled.eq(false)')->args);
        $this->assertSame([null], $this->parse('x.eq(null)')->args);
    }

    public function test_quoted_strings_with_escapes(): void
    {
        $this->assertSame(["it's"], $this->parse("name.eq('it\\'s')")->args);
        $this->assertSame(['a\\b'], $this->parse("name.eq('a\\\\b')")->args);
        $this->assertSame(['a, b)'], $this->parse("name.eq('a, b)')")->args);
    }

    public function test_lists_and_no_argument_calls(): void
    {
        $this->assertSame(['a', 'b', 'c'], $this->parse("name.in('a', 'b','c')")->args);
        $this->assertSame([], $this->parse('authorizedGuestLimit.isNull()')->args);
    }

    public function test_nested_combinators(): void
    {
        $node = $this->parse("and(enabled.eq(true), or(name.like('Gu*'), not(vlanId.eq(1))))");

        $this->assertSame('and', $node->kind);
        $this->assertCount(2, $node->children);
        $or = $node->children[1];
        $this->assertSame('or', $or->kind);
        $this->assertSame('not', $or->children[1]->kind);
        $this->assertSame('vlanId', $or->children[1]->children[0]->property);
    }

    public static function malformed(): array
    {
        return [
            'empty' => [''],
            'unclosed call' => ['name.eq('],
            'no function' => ["name('x')"],
            'unterminated string' => ["name.eq('x)"],
            'bare word value' => ['name.eq(Guest)'],
            'trailing input' => ["name.eq('x') extra"],
            'and with one child' => ["and(name.eq('x'))"],
            'not with two children' => ["not(name.eq('x'), name.eq('y'))"],
            'missing comma' => ["name.in('a' 'b')"],
            'malformed number' => ['vlanId.eq(-)'],
        ];
    }

    #[DataProvider('malformed')]
    public function test_each_syntax_error_is_a_400_filter_invalid(string $filter): void
    {
        try {
            $this->parse($filter);
            $this->fail("Parsed: {$filter}");
        } catch (MockError $e) {
            $this->assertSame(400, $e->status);
            $this->assertSame('api.request.invalid-filter', $e->errorCode);
        }
    }
}

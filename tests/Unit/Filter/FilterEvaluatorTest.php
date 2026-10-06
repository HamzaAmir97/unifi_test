<?php

namespace Tests\Unit\Filter;

use App\Mock\Errors\MockError;
use App\Mock\Filter\FilterEvaluator;
use App\Mock\Filter\FilterParser;
use PHPUnit\Framework\TestCase;

class FilterEvaluatorTest extends TestCase
{
    private const TABLE = [
        'id' => ['type' => 'UUID', 'functions' => ['eq', 'ne', 'in', 'notIn']],
        'name' => ['type' => 'STRING', 'functions' => ['eq', 'ne', 'in', 'notIn', 'like']],
        'vlanId' => ['type' => 'INTEGER', 'functions' => ['eq', 'ne', 'gt', 'ge', 'lt', 'le']],
        'limit' => ['type' => 'INTEGER', 'functions' => ['isNull', 'isNotNull', 'eq']],
        'ratio' => ['type' => 'DECIMAL', 'functions' => ['gt', 'lt', 'eq']],
        'enabled' => ['type' => 'BOOLEAN', 'functions' => ['eq', 'ne']],
        'createdAt' => ['type' => 'TIMESTAMP', 'functions' => ['gt', 'lt', 'eq']],
        'access.type' => ['type' => 'STRING', 'functions' => ['eq']],
        'networkIds' => ['type' => 'SET(UUID)', 'functions' => ['contains', 'containsAny', 'containsAll', 'containsExactly', 'isEmpty']],
        'tags' => ['type' => 'SET(STRING)', 'functions' => ['contains', 'isEmpty']],
        'members.deviceId' => ['type' => 'SET(UUID)', 'functions' => ['contains', 'containsAll']],
        'members.portIdxs' => ['type' => 'SET(INTEGER)', 'functions' => ['contains']],
    ];

    private const A = '11111111-1111-4111-8111-111111111111';

    private const B = '22222222-2222-4222-8222-222222222222';

    private function items(): array
    {
        return [
            ['id' => self::A, 'name' => 'Guest WiFi', 'vlanId' => 10, 'limit' => null, 'ratio' => 0.5, 'enabled' => true,
                'createdAt' => '2026-01-01T10:00:00.000Z', 'access' => ['type' => 'GUEST'], 'networkIds' => [self::A, self::B], 'tags' => [],
                'members' => [['deviceId' => self::A, 'portIdxs' => [1, 2]], ['deviceId' => self::B, 'portIdxs' => [3]]]],
            ['id' => self::B, 'name' => 'Office', 'vlanId' => 20, 'limit' => 3, 'ratio' => 1.25, 'enabled' => false,
                'createdAt' => '2026-02-01T10:00:00.000Z', 'access' => ['type' => 'DEFAULT'], 'networkIds' => [self::B], 'tags' => ['x'],
                'members' => [['deviceId' => self::B, 'portIdxs' => [9]]]],
        ];
    }

    private function names(string $filter): array
    {
        $result = (new FilterEvaluator(self::TABLE))->filter((new FilterParser)->parse($filter), $this->items());

        return array_column($result, 'name');
    }

    public function test_each_scalar_type(): void
    {
        $this->assertSame(['Office'], $this->names("id.eq('".strtoupper(self::B)."')"), 'UUIDs compare case-insensitively');
        $this->assertSame(['Guest WiFi'], $this->names("name.ne('Office')"));
        $this->assertSame(['Office'], $this->names('vlanId.gt(10)'));
        $this->assertSame(['Guest WiFi', 'Office'], $this->names('vlanId.ge(10)'));
        $this->assertSame(['Office'], $this->names('ratio.gt(1)'));
        $this->assertSame(['Guest WiFi'], $this->names('ratio.eq(0.5)'));
        $this->assertSame(['Office'], $this->names('enabled.eq(false)'));
        $this->assertSame(['Office'], $this->names("createdAt.gt('2026-01-15T00:00:00Z')"));
    }

    public function test_in_not_in_and_null_checks(): void
    {
        $this->assertSame(['Guest WiFi', 'Office'], $this->names("name.in('Office', 'Guest WiFi')"));
        $this->assertSame(['Guest WiFi'], $this->names("name.notIn('Office')"));
        $this->assertSame(['Guest WiFi'], $this->names('limit.isNull()'));
        $this->assertSame(['Office'], $this->names('limit.isNotNull()'));
    }

    public function test_like_with_wildcard(): void
    {
        $this->assertSame(['Guest WiFi'], $this->names("name.like('Gu*')"));
        $this->assertSame(['Guest WiFi'], $this->names("name.like('*WiFi')"));
        $this->assertSame([], $this->names("name.like('WiFi')"), 'like without a wildcard is an exact match');
    }

    public function test_dotted_properties(): void
    {
        $this->assertSame(['Guest WiFi'], $this->names("access.type.eq('GUEST')"));
    }

    public function test_set_functions(): void
    {
        $this->assertSame(['Guest WiFi'], $this->names("networkIds.contains('".self::A."')"));
        $this->assertSame(['Guest WiFi', 'Office'], $this->names("networkIds.containsAny('".self::A."', '".self::B."')"));
        $this->assertSame(['Guest WiFi'], $this->names("networkIds.containsAll('".self::A."', '".self::B."')"));
        $this->assertSame(['Office'], $this->names("networkIds.containsExactly('".self::B."')"));
        $this->assertSame(['Guest WiFi'], $this->names('tags.isEmpty()'));
    }

    public function test_paths_through_lists(): void
    {
        $this->assertSame(['Guest WiFi'], $this->names("members.deviceId.contains('".self::A."')"));
        $this->assertSame(['Guest WiFi'], $this->names("members.deviceId.containsAll('".self::A."', '".self::B."')"));
        $this->assertSame(['Guest WiFi'], $this->names('members.portIdxs.contains(3)'));
    }

    public function test_combinators(): void
    {
        $this->assertSame(['Office'], $this->names("and(vlanId.gt(5), not(name.like('Gu*')))"));
        $this->assertSame(['Guest WiFi', 'Office'], $this->names('or(enabled.eq(true), vlanId.eq(20))'));
    }

    public function test_an_undeclared_property_is_refused(): void
    {
        $this->expectRefusal("description.eq('x')");
    }

    public function test_an_undeclared_function_is_refused(): void
    {
        $this->expectRefusal("name.gt('a')");
    }

    public function test_a_wrongly_typed_argument_is_refused(): void
    {
        $this->expectRefusal("vlanId.eq('ten')");
    }

    public function test_wrong_arity_is_refused(): void
    {
        $this->expectRefusal("name.eq('a', 'b')");
    }

    public function test_a_refusal_inside_a_combinator_is_still_raised_on_an_empty_list(): void
    {
        $this->expectException(MockError::class);
        (new FilterEvaluator(self::TABLE))->filter((new FilterParser)->parse("and(name.eq('x'), nope.eq(1))"), []);
    }

    private function expectRefusal(string $filter): void
    {
        try {
            $this->names($filter);
            $this->fail("Accepted: {$filter}");
        } catch (MockError $e) {
            $this->assertSame(400, $e->status);
        }
    }
}

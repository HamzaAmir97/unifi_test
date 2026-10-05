<?php

namespace Tests\Unit\Pagination;

use App\Mock\Errors\MockError;
use App\Mock\Pagination\Paginator;
use App\Mock\Unverified;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    private function items(int $n): array
    {
        return array_map(fn ($i) => ['i' => $i], range(1, $n));
    }

    public function test_defaults(): void
    {
        $page = (new Paginator)->paginate($this->items(30), null, null);

        $this->assertSame(0, $page['offset']);
        $this->assertSame(25, $page['limit']);
        $this->assertSame(25, $page['count']);
        $this->assertSame(30, $page['totalCount']);
        $this->assertSame(['offset', 'limit', 'count', 'totalCount', 'data'], array_keys($page));
    }

    public function test_the_voucher_default_limit(): void
    {
        $page = (new Paginator)->paginate($this->items(150), null, null, Unverified::VOUCHER_DEFAULT_LIMIT);

        $this->assertSame(100, $page['limit']);
        $this->assertSame(100, $page['count']);
    }

    public function test_the_last_partial_page(): void
    {
        $page = (new Paginator)->paginate($this->items(30), '25', '10');

        $this->assertSame(5, $page['count']);
        $this->assertSame(30, $page['totalCount']);
        $this->assertSame(26, $page['data'][0]['i']);
    }

    public function test_an_offset_past_the_end_is_an_empty_page(): void
    {
        $page = (new Paginator)->paginate($this->items(3), '10', '5');

        $this->assertSame(0, $page['count']);
        $this->assertSame([], $page['data']);
    }

    public function test_the_maximum_is_accepted(): void
    {
        $page = (new Paginator)->paginate($this->items(3), '0', (string) Unverified::MAX_LIMIT);

        $this->assertSame(Unverified::MAX_LIMIT, $page['limit']);
    }

    public static function invalid(): array
    {
        return [
            'negative offset' => ['-1', '10'],
            'zero limit' => ['0', '0'],
            'limit above max' => ['0', (string) (Unverified::MAX_LIMIT + 1)],
            'non-numeric offset' => ['abc', '10'],
            'non-numeric limit' => ['0', '1.5'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_invalid_input_is_a_400(string $offset, string $limit): void
    {
        $this->expectException(MockError::class);
        (new Paginator)->paginate($this->items(3), $offset, $limit);
    }
}

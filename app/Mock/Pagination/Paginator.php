<?php

namespace App\Mock\Pagination;

use App\Mock\Errors\MockError;
use App\Mock\Unverified;

/**
 * The list envelope shared by every list (FR-007): offset, limit, count, totalCount, data.
 * Defaults and maximum are UNVERIFIED (G6).
 */
final class Paginator
{
    /**
     * @param  list<array<string, mixed>>  $items  already filtered, in final order
     * @return array{offset: int, limit: int, count: int, totalCount: int, data: list<array<string, mixed>>}
     */
    public function paginate(array $items, mixed $offset, mixed $limit, int $defaultLimit = Unverified::DEFAULT_LIMIT): array
    {
        $offset = $this->integer($offset, 'offset', 0);
        $limit = $this->integer($limit, 'limit', $defaultLimit);

        if ($offset < 0) {
            throw MockError::invalid('offset must be 0 or greater.');
        }
        if ($limit < 1 || $limit > Unverified::MAX_LIMIT) {
            throw MockError::invalid('limit must be between 1 and '.Unverified::MAX_LIMIT.'.');
        }

        $page = array_slice($items, $offset, $limit);

        return [
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($page),
            'totalCount' => count($items),
            'data' => array_values($page),
        ];
    }

    private function integer(mixed $value, string $name, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        throw MockError::invalid("{$name} must be an integer.");
    }
}

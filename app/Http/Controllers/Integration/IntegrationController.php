<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Mock\Errors\MockError;
use App\Mock\Filter\FilterEvaluator;
use App\Mock\Filter\FilterParser;
use App\Mock\Manifest\Manifest;
use App\Mock\Pagination\Paginator;
use App\Mock\Store\ResourceStore;
use App\Mock\Unverified;
use App\Models\MockResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Shared steps of the integration controllers: resolve the site, find a resource inside
 * it, answer a list with filter + pagination from the manifest, answer an empty text body.
 */
abstract class IntegrationController extends Controller
{
    public function __construct(
        protected readonly ResourceStore $store,
        protected readonly Manifest $manifest,
    ) {}

    protected function site(string $siteId): MockResource
    {
        return $this->store->find(ResourceType::Site, null, $siteId)
            ?? throw MockError::notFound("Site {$siteId} not found.");
    }

    protected function findIn(ResourceType $type, string $siteId, string $id, string $label): MockResource
    {
        $this->site($siteId);

        return $this->store->find($type, $siteId, $id)
            ?? throw MockError::notFound("{$label} {$id} not found.");
    }

    /**
     * Filter (only what the manifest declares for $entryId) then paginate.
     *
     * @param  list<array<string, mixed>>  $items  presented objects, in order
     */
    protected function listResponse(Request $request, string $entryId, array $items): JsonResponse
    {
        return new JsonResponse($this->page($request, $entryId, $items));
    }

    /** @param list<array<string, mixed>> $items */
    protected function page(Request $request, string $entryId, array $items): array
    {
        $items = $this->filtered($request, $entryId, $items);
        $entry = $this->manifest->entry($entryId);

        return (new Paginator)->paginate(
            $items,
            $request->query('offset'),
            $request->query('limit'),
            $entry['defaultLimit'] ?? Unverified::DEFAULT_LIMIT,
        );
    }

    /** @param list<array<string, mixed>> $items */
    protected function filtered(Request $request, string $entryId, array $items): array
    {
        $filter = $request->query('filter');
        if ($filter === null) {
            return $items;
        }
        if (! is_string($filter)) {
            throw MockError::filterInvalid('filter must be a single string.');
        }

        $evaluator = new FilterEvaluator($this->manifest->filterTable($entryId));

        return $evaluator->filter((new FilterParser)->parse($filter), $items);
    }

    protected function emptyText(): Response
    {
        return new Response('', 200, ['Content-Type' => 'text/plain']);
    }

    protected function created(array $body): JsonResponse
    {
        return new JsonResponse($body, 201);
    }
}

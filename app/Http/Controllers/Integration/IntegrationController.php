<?php

namespace App\Http\Controllers\Integration;

use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Mock\Errors\MockError;
use App\Mock\Filter\FilterEvaluator;
use App\Mock\Filter\FilterParser;
use App\Mock\Manifest\Manifest;
use App\Mock\Pagination\Paginator;
use App\Mock\Presenters\Presenter;
use App\Mock\Store\ResourceStore;
use App\Mock\Unverified;
use App\Models\MockResource;
use Closure;
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
     * A list: filter on each resource's full view (only what the manifest declares for
     * $entryId), paginate, then present the page.
     *
     * @param  iterable<MockResource>  $resources  in order
     * @param  (Closure(MockResource): array<string, mixed>)|null  $present  defaults to $presenter->present()
     */
    protected function listResponse(Request $request, string $entryId, iterable $resources, Presenter $presenter, ?Closure $present = null): JsonResponse
    {
        $rows = [];
        foreach ($resources as $resource) {
            $rows[] = ['resource' => $resource, 'view' => $presenter->view($resource)];
        }
        $rows = $this->filtered($request, $entryId, $rows);

        $page = (new Paginator)->paginate(
            $rows,
            $request->query('offset'),
            $request->query('limit'),
            $this->manifest->entry($entryId)['defaultLimit'] ?? Unverified::DEFAULT_LIMIT,
        );
        $present ??= fn (MockResource $r) => $presenter->present($r);
        $page['data'] = array_map(fn ($row) => $present($row['resource']), $page['data']);

        return new JsonResponse($page);
    }

    /**
     * @param  list<array{resource: MockResource, view: array<string, mixed>}>  $rows
     * @return list<array{resource: MockResource, view: array<string, mixed>}>
     */
    protected function filtered(Request $request, string $entryId, array $rows, bool $required = false): array
    {
        $filter = $request->query('filter');
        if ($filter === null || $filter === '') {
            if ($required) {
                throw MockError::invalid('The filter parameter is required.');
            }

            return $rows;
        }
        if (! is_string($filter)) {
            throw MockError::filterInvalid('filter must be a single string.');
        }

        $evaluator = new FilterEvaluator($this->manifest->filterTable($entryId));
        $node = (new FilterParser)->parse($filter);
        $evaluator->validate($node);

        return array_values(array_filter($rows, fn ($row) => $evaluator->matches($node, $row['view'])));
    }

    /** The `force` query parameter: absent = false; otherwise it must be a boolean. */
    protected function force(Request $request): bool
    {
        $force = $request->query('force');
        if ($force === null || $force === '') {
            return false;
        }

        return match (strtolower((string) $force)) {
            'true' => true,
            'false' => false,
            default => throw MockError::invalid('force must be true or false.'),
        };
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

<?php

namespace App\Mock\Store;

use App\Enums\ResourceType;
use App\Models\MockResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * All reads and writes of `mock_resources`. A site-scoped lookup never returns another
 * site's resource (spec edge case "missing or wrong site"). Every write is one transaction.
 */
final class ResourceStore
{
    /** @return Collection<int, MockResource> */
    public function all(ResourceType $type, ?string $siteUuid = null): Collection
    {
        return $this->scoped($type, $siteUuid)->orderBy('position')->orderBy('id')->get();
    }

    public function find(ResourceType $type, ?string $siteUuid, string $uuid): ?MockResource
    {
        return $this->scoped($type, $siteUuid)->where('uuid', $uuid)->first();
    }

    public function exists(ResourceType $type, ?string $siteUuid, string $uuid): bool
    {
        return $this->scoped($type, $siteUuid)->where('uuid', $uuid)->exists();
    }

    public function create(ResourceType $type, ?string $siteUuid, array $payload, ?string $uuid = null): MockResource
    {
        return DB::transaction(fn () => MockResource::query()->create([
            'type' => $type->value,
            'site_uuid' => $type->isGlobal() ? null : $siteUuid,
            'uuid' => $uuid ?? (string) Str::uuid(),
            'payload' => $payload,
            'position' => $this->nextPosition($type, $siteUuid),
        ]));
    }

    public function replace(MockResource $resource, array $payload): MockResource
    {
        return DB::transaction(function () use ($resource, $payload) {
            $resource->payload = $payload;
            $resource->save();

            return $resource;
        });
    }

    /** Merge: keys present in $changes overwrite, everything else is kept (PATCH). */
    public function patch(MockResource $resource, array $changes): MockResource
    {
        return $this->replace($resource, array_replace($resource->payload, $changes));
    }

    public function delete(MockResource $resource): void
    {
        DB::transaction(fn () => $resource->delete());
    }

    /** @param iterable<MockResource> $resources */
    public function deleteMany(iterable $resources): int
    {
        return DB::transaction(function () use ($resources) {
            $n = 0;
            foreach ($resources as $resource) {
                $resource->delete();
                $n++;
            }

            return $n;
        });
    }

    /** Rewrite positions in the given order (ordering endpoints). @param list<string> $uuids */
    public function reorder(ResourceType $type, ?string $siteUuid, array $uuids): void
    {
        DB::transaction(function () use ($type, $siteUuid, $uuids) {
            foreach ($uuids as $i => $uuid) {
                $this->scoped($type, $siteUuid)->where('uuid', $uuid)->update(['position' => $i + 1]);
            }
        });
    }

    public function nextPosition(ResourceType $type, ?string $siteUuid): int
    {
        return (int) $this->scoped($type, $siteUuid)->max('position') + 1;
    }

    public function truncate(): void
    {
        MockResource::query()->delete();
    }

    private function scoped(ResourceType $type, ?string $siteUuid)
    {
        $query = MockResource::query()->where('type', $type->value);

        return $type->isGlobal() ? $query : $query->where('site_uuid', $siteUuid);
    }
}

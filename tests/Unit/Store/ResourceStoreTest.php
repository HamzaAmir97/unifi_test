<?php

namespace Tests\Unit\Store;

use App\Enums\ResourceType;
use App\Mock\Store\ResourceStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceStoreTest extends TestCase
{
    use RefreshDatabase;

    private function store(): ResourceStore
    {
        return app(ResourceStore::class);
    }

    public function test_site_isolation(): void
    {
        $a = $this->seedSite('A');
        $b = $this->seedSite('B');
        $net = $this->store()->create(ResourceType::Network, $a->uuid, ['name' => 'LAN']);

        $this->assertNotNull($this->store()->find(ResourceType::Network, $a->uuid, $net->uuid));
        $this->assertNull($this->store()->find(ResourceType::Network, $b->uuid, $net->uuid), 'another site never sees it');
        $this->assertCount(0, $this->store()->all(ResourceType::Network, $b->uuid));
    }

    public function test_a_uuid_is_generated_and_positions_are_stable(): void
    {
        $site = $this->seedSite();
        $first = $this->store()->create(ResourceType::Network, $site->uuid, ['name' => 'one']);
        $second = $this->store()->create(ResourceType::Network, $site->uuid, ['name' => 'two']);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $first->uuid);
        $this->assertSame([1, 2], [$first->position, $second->position]);
        $this->assertSame(['one', 'two'], $this->store()->all(ResourceType::Network, $site->uuid)->pluck('payload.name')->all());
    }

    public function test_global_types_ignore_the_site(): void
    {
        $country = $this->store()->create(ResourceType::Country, 'ignored', ['code' => 'SA', 'name' => 'Saudi Arabia']);

        $this->assertNull($country->site_uuid);
        $this->assertNotNull($this->store()->find(ResourceType::Country, null, $country->uuid));
    }

    public function test_patch_merges_and_replace_replaces(): void
    {
        $site = $this->seedSite();
        $r = $this->store()->create(ResourceType::Network, $site->uuid, ['name' => 'LAN', 'enabled' => true]);

        $this->store()->patch($r, ['enabled' => false]);
        $this->assertSame(['name' => 'LAN', 'enabled' => false], $r->fresh()->payload);

        $this->store()->replace($r, ['name' => 'X']);
        $this->assertSame(['name' => 'X'], $r->fresh()->payload);
    }

    public function test_reorder(): void
    {
        $site = $this->seedSite();
        $a = $this->store()->create(ResourceType::AclRule, $site->uuid, ['name' => 'a']);
        $b = $this->store()->create(ResourceType::AclRule, $site->uuid, ['name' => 'b']);

        $this->store()->reorder(ResourceType::AclRule, $site->uuid, [$b->uuid, $a->uuid]);

        $this->assertSame(['b', 'a'], $this->store()->all(ResourceType::AclRule, $site->uuid)->pluck('payload.name')->all());
    }
}

<?php

namespace Tests\Feature\Core;

use App\Mock\Manifest\Manifest;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** R4: the routes and the manifest match one-to-one, in both directions. */
class RouteManifestParityTest extends TestCase
{
    private const PREFIX = 'v1/connector/consoles/{consoleId}/proxy/network/integration';

    public function test_every_manifest_entry_has_its_route(): void
    {
        foreach (app(Manifest::class)->entries() as $entry) {
            $route = Route::getRoutes()->getByName($entry['id']);
            $this->assertNotNull($route, "No route for {$entry['id']}");
            $this->assertContains($entry['method'], $route->methods(), $entry['id']);
            $this->assertSame(self::PREFIX.$entry['path'], $route->uri(), $entry['id']);
        }
    }

    public function test_every_integration_route_is_in_the_manifest(): void
    {
        $ids = array_column(app(Manifest::class)->entries(), 'id');
        $integration = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), self::PREFIX));

        $this->assertCount(73, $integration);
        foreach ($integration as $route) {
            $this->assertContains($route->getName(), $ids, $route->uri());
        }
    }

    public function test_ordering_routes_are_declared_before_their_id_siblings(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => implode(',', $r->methods()).' '.$r->uri())->values()->all();
        $index = fn (string $needle) => collect($uris)->search(fn ($u) => str_ends_with($u, $needle));

        foreach (['firewall/policies' => '{firewallPolicyId}', 'acl-rules' => '{aclRuleId}'] as $base => $param) {
            $ordering = $index("/{$base}/ordering");
            $id = $index("/{$base}/{$param}");
            $this->assertNotFalse($ordering);
            $this->assertNotFalse($id);
            $this->assertLessThan($id, $ordering, "{$base}/ordering must be declared before {$param}");
        }
    }
}

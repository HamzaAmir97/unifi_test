<?php

namespace Tests\Feature\Control;

use App\Mock\OpenApi\ControlPlaneSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FR-018 / US4 scenario 5: the control plane does not exist in production. */
class ProductionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_control_route_is_404_in_production(): void
    {
        $this->app['env'] = 'production';

        foreach (ControlPlaneSpec::operations() as $op) {
            $this->assertEnvelope($this->json($op['method'], '/__mock'.$op['path'], $op['body'] ?? []), 404);
        }
        $this->unifi('GET', '/v1/info')->assertOk();
    }

    public function test_reachable_outside_production(): void
    {
        $this->control('GET', '/state')->assertOk();
    }

    public function test_every_control_route_is_described_once(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), '__mock'))
            ->map(fn ($r) => $r->methods()[0].' '.substr($r->uri(), strlen('__mock')))
            ->sort()->values()->all();
        $described = collect(ControlPlaneSpec::operations())->map(fn ($o) => $o['method'].' '.$o['path'])->sort()->values()->all();

        $this->assertSame($described, $routes, 'Swagger and the Postman control collection must list exactly the control routes');
    }
}

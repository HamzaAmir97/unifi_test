<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Mock\Clock\MockClock;
use App\Mock\Faults\FaultInjector;
use App\Mock\Scenarios\ScenarioRunner;
use App\Mock\Settings\MockSettings;
use App\Models\MockResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /__mock/reset?seed= and GET /__mock/state. */
class StateController extends Controller
{
    public function reset(Request $request, ScenarioRunner $runner): JsonResponse
    {
        $scenario = (string) $request->query('seed', 'default');

        return new JsonResponse(['scenario' => $scenario, 'counts' => (object) $runner->run($scenario)]);
    }

    public function show(MockClock $clock, MockSettings $settings, FaultInjector $faults): JsonResponse
    {
        $resources = [];
        foreach (MockResource::query()->orderBy('type')->orderBy('position')->get() as $r) {
            $resources[$r->type][] = ['id' => $r->uuid, 'siteId' => $r->site_uuid] + $r->payload;
        }

        return new JsonResponse([
            'clock' => ['now' => $clock->now()->format('Y-m-d\TH:i:s.v\Z')] + $clock->state(),
            'strict' => $settings->strict(),
            'optionalFields' => $settings->optionalFields()->value,
            'faults' => array_map(fn ($f) => $f->toArray(), $faults->all()),
            'resources' => (object) $resources,
        ]);
    }
}

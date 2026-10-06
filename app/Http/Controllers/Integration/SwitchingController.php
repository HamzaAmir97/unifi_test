<?php

namespace App\Http\Controllers\Integration;

use App\Mock\Devices\SwitchingTopology;
use App\Mock\Errors\MockError;
use App\Mock\Presenters\PayloadPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Switching group: read only, derived from the site's devices on every request. */
class SwitchingController extends IntegrationController
{
    public function lags(Request $request, string $siteId, SwitchingTopology $topology): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, 'list-lags', $topology->lags($siteId), PayloadPresenter::for('get-lag-details'));
    }

    public function lag(string $siteId, string $lagId, SwitchingTopology $topology): JsonResponse
    {
        $this->site($siteId);

        return new JsonResponse(PayloadPresenter::for('get-lag-details')->present($this->pick($topology->lags($siteId), $lagId, 'LAG')));
    }

    public function mcLagDomains(Request $request, string $siteId, SwitchingTopology $topology): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, 'list-mc-lag-domains', $topology->mcLagDomains($siteId), PayloadPresenter::for('get-mc-lag-domain'));
    }

    public function mcLagDomain(string $siteId, string $mcLagDomainId, SwitchingTopology $topology): JsonResponse
    {
        $this->site($siteId);

        return new JsonResponse(PayloadPresenter::for('get-mc-lag-domain')->present($this->pick($topology->mcLagDomains($siteId), $mcLagDomainId, 'MC-LAG domain')));
    }

    public function switchStacks(Request $request, string $siteId, SwitchingTopology $topology): JsonResponse
    {
        $this->site($siteId);

        return $this->listResponse($request, 'list-switch-stacks', $topology->switchStacks($siteId), PayloadPresenter::for('get-switch-stack'));
    }

    public function switchStack(string $siteId, string $switchStackId, SwitchingTopology $topology): JsonResponse
    {
        $this->site($siteId);

        return new JsonResponse(PayloadPresenter::for('get-switch-stack')->present($this->pick($topology->switchStacks($siteId), $switchStackId, 'Switch stack')));
    }

    private function pick(Collection $items, string $id, string $label)
    {
        return $items->first(fn ($i) => $i->uuid === $id) ?? throw MockError::notFound("{$label} {$id} not found.");
    }
}

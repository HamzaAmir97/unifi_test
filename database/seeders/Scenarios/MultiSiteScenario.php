<?php

namespace Database\Seeders\Scenarios;

use App\Mock\Scenarios\Scenario;
use App\Mock\Scenarios\ScenarioKit;

/** Two sites, each with its own networks, broadcasts, clients and vouchers. */
final class MultiSiteScenario implements Scenario
{
    public function build(ScenarioKit $kit): void
    {
        $b = new SiteBuilder($kit);
        $infra = new InfrastructureBuilder($kit);
        foreach ([['APEX Coworking', 'default', 0], ['APEX Branch', 'branch', 1]] as [$name, $ref, $block]) {
            $site = $b->site($name, $ref);
            $nets = $b->network($site, $block);
            $b->voucherStates($site);
            $infra->build($site, $nets, $block);
        }
        $infra->pendingDevice('pending-ap', 'U7-Lite', 1);
        $infra->pendingDevice('pending-switch', 'USW-Lite-8-PoE', 2);
        ReferenceData::build($kit);
    }
}

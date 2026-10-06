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
        foreach ([['APEX Coworking', 'default', 0], ['APEX Branch', 'branch', 1]] as [$name, $ref, $block]) {
            $site = $b->site($name, $ref);
            $b->network($site, $block);
            $b->voucherStates($site);
        }
    }
}

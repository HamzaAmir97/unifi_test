<?php

namespace Database\Seeders\Scenarios;

use App\Mock\Scenarios\Scenario;
use App\Mock\Scenarios\ScenarioKit;

/** One site: LAN, members and guest networks, a hotspot broadcast, clients, vouchers in every state. */
final class DefaultScenario implements Scenario
{
    public function build(ScenarioKit $kit): void
    {
        $b = new SiteBuilder($kit);
        $site = $b->site('APEX Coworking', 'default');
        $b->network($site);
        $b->voucherStates($site);
    }
}

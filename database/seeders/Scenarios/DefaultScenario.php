<?php

namespace Database\Seeders\Scenarios;

use App\Mock\Scenarios\Scenario;
use App\Mock\Scenarios\ScenarioKit;

/**
 * One site: LAN, members, guest and lab networks, a hotspot broadcast, clients, vouchers in every
 * state, six devices (stack + MC-LAG + LAGs), firewall, ACL, DNS, reference data, one pending device.
 */
final class DefaultScenario implements Scenario
{
    public function build(ScenarioKit $kit): void
    {
        $b = new SiteBuilder($kit);
        $site = $b->site('APEX Coworking', 'default');
        $nets = $b->network($site);
        $b->voucherStates($site);
        $infra = new InfrastructureBuilder($kit);
        $infra->build($site, $nets);
        $infra->pendingDevice('pending-ap', 'U7-Lite', 1);
        ReferenceData::build($kit);
    }
}

<?php

namespace Database\Seeders\Scenarios;

use App\Mock\Scenarios\Scenario;
use App\Mock\Scenarios\ScenarioKit;

/** The default site with 300 vouchers, for pagination (T6 in the plan). */
final class LargeScenario implements Scenario
{
    public const VOUCHERS = 300;

    public function build(ScenarioKit $kit): void
    {
        $b = new SiteBuilder($kit);
        $site = $b->site('APEX Coworking', 'default');
        $b->network($site);
        $b->manyVouchers($site, self::VOUCHERS);
    }
}

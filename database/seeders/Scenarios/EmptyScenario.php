<?php

namespace Database\Seeders\Scenarios;

use App\Mock\Scenarios\Scenario;
use App\Mock\Scenarios\ScenarioKit;

/** Nothing at all: no site, so every site-scoped request answers 404. */
final class EmptyScenario implements Scenario
{
    public function build(ScenarioKit $kit): void {}
}

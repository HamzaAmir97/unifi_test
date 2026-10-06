<?php

namespace App\Mock\Scenarios;

/** A named, reproducible starting state (R15). Builds from empty. */
interface Scenario
{
    public function build(ScenarioKit $kit): void;
}

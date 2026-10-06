<?php

namespace App\Console\Commands;

use App\Mock\Errors\MockError;
use App\Mock\Scenarios\ScenarioRunner;
use Illuminate\Console\Command;

class ResetMock extends Command
{
    protected $signature = 'mock:reset {scenario=default : default, empty, multi-site or large}';

    protected $description = 'Replace all mock state with a scenario, reset the clock and clear faults';

    public function handle(ScenarioRunner $runner): int
    {
        try {
            $counts = $runner->run($this->argument('scenario'));
        } catch (MockError $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Scenario '{$this->argument('scenario')}' loaded.");
        $this->table(['type', 'count'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values()->all());

        return self::SUCCESS;
    }
}

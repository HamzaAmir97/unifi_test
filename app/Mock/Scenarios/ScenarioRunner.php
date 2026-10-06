<?php

namespace App\Mock\Scenarios;

use App\Mock\Clock\MockClock;
use App\Mock\Errors\MockError;
use App\Mock\Settings\MockSettings;
use App\Mock\Store\ResourceStore;
use App\Models\MockResource;
use Database\Seeders\Scenarios\DefaultScenario;
use Database\Seeders\Scenarios\EmptyScenario;
use Database\Seeders\Scenarios\LargeScenario;
use Database\Seeders\Scenarios\MultiSiteScenario;
use Illuminate\Support\Facades\DB;

/**
 * The one service both `php artisan mock:reset` and `POST /__mock/reset` call (R15).
 * A reset replaces all state, returns the clock to real time, clears faults and restores
 * the default settings, so the same scenario always gives the same start (SC-008).
 */
final class ScenarioRunner
{
    public const SCENARIOS = [
        'default' => DefaultScenario::class,
        'empty' => EmptyScenario::class,
        'multi-site' => MultiSiteScenario::class,
        'large' => LargeScenario::class,
    ];

    public function __construct(
        private readonly ResourceStore $store,
        private readonly MockSettings $settings,
        private readonly MockClock $clock,
    ) {}

    /** @return array<string, int> resource counts by type */
    public function run(string $name): array
    {
        $class = self::SCENARIOS[$name] ?? throw MockError::invalid("Unknown scenario '{$name}'. Known: ".implode(', ', array_keys(self::SCENARIOS)).'.');

        DB::transaction(function () use ($class) {
            $this->store->truncate();
            $this->settings->restoreDefaults();
            (new $class)->build(new ScenarioKit($this->store, $this->clock->now()));
        });

        return MockResource::query()->selectRaw('type, count(*) as n')->groupBy('type')->orderBy('type')->pluck('n', 'type')->map(fn ($n) => (int) $n)->all();
    }
}

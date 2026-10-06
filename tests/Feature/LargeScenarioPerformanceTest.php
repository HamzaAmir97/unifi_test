<?php

namespace Tests\Feature;

use App\Mock\Scenarios\ScenarioRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan performance goal: any list over the `large` scenario answers in under 500 ms on the
 * developer machine. Environment-dependent by nature; measured on the in-memory test DB.
 */
class LargeScenarioPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_300_voucher_list_and_a_filter_answer_quickly(): void
    {
        app(ScenarioRunner::class)->run('large');
        $site = $this->unifi('GET', '/v1/sites')->json('data.0.id');

        foreach (['?limit=200', '?offset=200&limit=100', '?filter='.urlencode("and(authorizedGuestLimit.ge(2), name.like('Event*'))")] as $query) {
            $start = microtime(true);
            $this->unifi('GET', "/v1/sites/{$site}/hotspot/vouchers{$query}")->assertOk();
            $this->assertLessThan(0.5, microtime(true) - $start, $query);
        }
    }
}

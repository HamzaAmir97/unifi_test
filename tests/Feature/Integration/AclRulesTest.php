<?php

namespace Tests\Feature\Integration;

use App\Mock\Manifest\Manifest;
use App\Mock\Scenarios\ScenarioRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AclRulesTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScenarioRunner::class)->run('default');
        $this->site = $this->unifi('GET', '/v1/sites')->json('data.0.id');
    }

    public function test_crud(): void
    {
        $created = $this->unifi('POST', "/v1/sites/{$this->site}/acl-rules", ['name' => 'New', 'enabled' => true, 'type' => 'IPV4', 'action' => 'ALLOW'])->assertCreated()->json();
        $this->assertSame(4, $created['index']);

        $this->unifi('PUT', "/v1/sites/{$this->site}/acl-rules/{$created['id']}", ['name' => 'Renamed', 'enabled' => false, 'type' => 'IPV4', 'action' => 'BLOCK'])
            ->assertOk()->assertJsonPath('action', 'BLOCK');
        $this->unifi('DELETE', "/v1/sites/{$this->site}/acl-rules/{$created['id']}")->assertOk();
        $this->assertEnvelope($this->unifi('GET', "/v1/sites/{$this->site}/acl-rules/{$created['id']}"), 404);
    }

    public function test_all_23_filter_properties_are_accepted(): void
    {
        $samples = ['STRING' => "'x'", 'UUID' => "'00000000-0000-4000-8000-000000000000'", 'BOOLEAN' => 'true', 'INTEGER' => '1',
            'SET(STRING)' => "'x'", 'SET(UUID)' => "'00000000-0000-4000-8000-000000000000'", 'SET(INTEGER)' => '1'];
        $table = app(Manifest::class)->filterTable('list-acl-rules');
        $this->assertCount(23, $table);

        foreach ($table as $name => $p) {
            $this->unifi('GET', "/v1/sites/{$this->site}/acl-rules?filter=".urlencode("{$name}.{$p['functions'][0]}(".(in_array($p['functions'][0], ['isNull', 'isNotNull', 'isEmpty'], true) ? '' : $samples[$p['type']]).')'))->assertOk();
        }
    }

    public function test_filters_reach_nested_and_set_properties(): void
    {
        $this->assertSame(1, $this->unifi('GET', "/v1/sites/{$this->site}/acl-rules?filter=".urlencode('destinationFilter.portFilter.contains(9100)'))->json('totalCount'));
        $this->assertSame(1, $this->unifi('GET', "/v1/sites/{$this->site}/acl-rules?filter=".urlencode("sourceFilter.type.eq('MAC')"))->json('totalCount'));
    }

    public function test_ordering_and_reorder(): void
    {
        $ids = $this->unifi('GET', "/v1/sites/{$this->site}/acl-rules/ordering")->assertOk()->json('orderedAclRuleIds');
        $this->assertCount(3, $ids);

        $reversed = array_reverse($ids);
        $this->unifi('PUT', "/v1/sites/{$this->site}/acl-rules/ordering", ['orderedAclRuleIds' => $reversed])->assertOk()->assertExactJson(['orderedAclRuleIds' => $reversed]);
        $this->assertSame(1, $this->unifi('GET', "/v1/sites/{$this->site}/acl-rules/{$reversed[0]}")->json('index'));
    }

    public function test_reorder_with_a_different_set_is_refused(): void
    {
        $ids = $this->unifi('GET', "/v1/sites/{$this->site}/acl-rules/ordering")->json('orderedAclRuleIds');

        $this->assertEnvelope($this->unifi('PUT', "/v1/sites/{$this->site}/acl-rules/ordering", ['orderedAclRuleIds' => array_slice($ids, 1)]), 400);
        $this->assertEnvelope($this->unifi('PUT', "/v1/sites/{$this->site}/acl-rules/ordering", ['orderedAclRuleIds' => [$ids[0], $ids[0], $ids[1]]]), 400);
    }
}

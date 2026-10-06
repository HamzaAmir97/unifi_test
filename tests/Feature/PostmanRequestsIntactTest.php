<?php

namespace Tests\Feature;

use App\Console\Commands\ImportCollection;
use App\Mock\Postman\PostmanTests;
use Tests\TestCase;

/**
 * FR-021 / SC-003 / R16: adding pm.test scripts and the read-only folder must never alter a
 * request. Every request (method, url, headers, body, params, description, examples) is
 * compared with the projection written when the collection was imported.
 */
class PostmanRequestsIntactTest extends TestCase
{
    private function collection(): array
    {
        return json_decode(file_get_contents(base_path(ImportCollection::TARGET)), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_requests_are_byte_identical_to_the_import(): void
    {
        $snapshot = json_decode(file_get_contents(base_path(ImportCollection::SNAPSHOT)), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(73, $snapshot);
        $this->assertSame($snapshot, ImportCollection::requestProjection($this->collection()));
    }

    public function test_every_request_carries_its_status_check(): void
    {
        foreach ($this->collection()['item'] as $group) {
            foreach ($group['item'] as $item) {
                $script = implode("\n", $item['event'][0]['script']['exec'] ?? []);
                $this->assertStringContainsString('pm.response.to.have.status(', $script, "{$group['name']} / {$item['name']}");
            }
        }
    }

    public function test_the_read_only_folder_holds_only_gets_needing_at_most_a_site(): void
    {
        $folder = collect($this->collection()['item'])->firstWhere('name', PostmanTests::READ_ONLY_FOLDER);

        $this->assertNotNull($folder);
        $this->assertNotEmpty($folder['item']);
        foreach ($folder['item'] as $item) {
            $this->assertSame('GET', $item['request']['method'], $item['name']);
            $vars = array_column($item['request']['url']['variable'] ?? [], 'key');
            $this->assertSame([], array_values(array_diff($vars, ['siteId', 'consoleId'])), $item['name']);
        }
    }

    public function test_applying_the_tests_again_changes_nothing(): void
    {
        $collection = $this->collection();

        $this->assertSame($collection, app(PostmanTests::class)->apply($collection));
    }

    public function test_the_mock_reset_only_runs_on_the_mock(): void
    {
        $pre = implode("\n", $this->collection()['event'][0]['script']['exec']);
        $production = array_column(json_decode(file_get_contents(base_path('postman/unifi-production.postman_environment.json')), true)['values'], 'key');

        $this->assertStringContainsString("pm.variables.get('resetBeforeEach') === 'true'", $pre);
        $this->assertNotContains('resetBeforeEach', $production);
        $this->assertNotContains('controlBase', $production);
    }

    public function test_the_mock_environment_is_generated_from_the_default_scenario(): void
    {
        $committed = file_get_contents(base_path('postman/unifi-mock.postman_environment.json'));
        $this->artisan('migrate:fresh');
        $this->artisan('mock:build-mock-environment')->assertSuccessful();

        $this->assertSame($committed, file_get_contents(base_path('postman/unifi-mock.postman_environment.json')));
    }
}

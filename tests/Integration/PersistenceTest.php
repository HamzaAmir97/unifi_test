<?php

namespace Tests\Integration;

use App\Enums\ResourceType;
use App\Mock\Settings\MockSettings;
use App\Mock\Store\ResourceStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FR-003 / SC-008: state survives a restart. An in-memory database cannot prove this, so
 * this test uses a real SQLite file and drops the connection between write and read.
 */
class PersistenceTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'unifi-mock-').'.sqlite';
        touch($this->file);
        config(['database.connections.sqlite.database' => $this->file]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_resources_and_settings_survive_a_reconnect(): void
    {
        $site = app(ResourceStore::class)->create(ResourceType::Site, null, ['internalReference' => 'default', 'name' => 'Default']);
        app(MockSettings::class)->put(MockSettings::STRICT, true);

        DB::purge('sqlite'); // the "restart": a new connection to the same file

        $this->assertNotNull(app(ResourceStore::class)->find(ResourceType::Site, null, $site->uuid));
        $this->assertTrue(app(MockSettings::class)->strict());
    }
}

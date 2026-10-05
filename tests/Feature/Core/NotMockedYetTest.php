<?php

namespace Tests\Feature\Core;

use App\Mock\Manifest\Manifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * TEMPORARY (G1–G3): every manifest entry with no controller yet answers 501, so it never
 * looks like a wrong URL. The expected set narrows as groups are built; the file is deleted
 * in G3.14 together with NotMockedYetController.
 */
class NotMockedYetTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_unbuilt_entry_answers_501(): void
    {
        $site = $this->seedSite();
        $unbuilt = collect(app(Manifest::class)->entries())
            ->filter(fn ($e) => Route::getRoutes()->getByName($e['id'])->getActionName() === 'App\Http\Controllers\Integration\NotMockedYetController');

        $this->assertNotEmpty($unbuilt);
        foreach ($unbuilt as $entry) {
            $path = str_replace('{siteId}', $site->uuid, $entry['path']);
            $path = preg_replace('/\{[^}]+\}/', '00000000-0000-4000-8000-000000000000', $path);
            $response = $this->unifi($entry['method'], $path);
            $this->assertEnvelope($response, 501);
            $this->assertSame('api.mock.not-mocked-yet', $response->json('code'), $entry['id']);
        }
    }
}

<?php

use App\Http\Controllers\Integration\InfoController;
use App\Http\Controllers\Integration\NotMockedYetController;
use App\Http\Controllers\Integration\SiteController;
use App\Mock\Manifest\Manifest;
use Illuminate\Support\Facades\Route;

/*
 * The 73 requests of the UniFi Network API collection, declared one by one (R4) so that
 * `php artisan route:list` shows the contract. Prefix (bootstrap/app.php):
 *   v1/connector/consoles/{consoleId}/proxy/network/integration
 * Each route is named after its manifest entry; RouteManifestParityTest pins the match.
 */

// Application Info · Sites
Route::get('v1/info', [InfoController::class, 'show'])->name('get-application-info');
Route::get('v1/sites', [SiteController::class, 'index'])->name('list-local-sites');

/*
 * TEMPORARY (G1–G3): every manifest entry not declared above answers 501 NOT_MOCKED_YET.
 * Removed in G3.14.
 */
$declared = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getName())->filter()->all();
foreach (app(Manifest::class)->entries() as $entry) {
    if (! in_array($entry['id'], $declared, true)) {
        Route::match([$entry['method']], ltrim($entry['path'], '/'), NotMockedYetController::class)
            ->defaults('manifestEntry', $entry['id'])
            ->name($entry['id']);
    }
}

<?php

use App\Http\Controllers\Integration\ClientController;
use App\Http\Controllers\Integration\InfoController;
use App\Http\Controllers\Integration\NetworkController;
use App\Http\Controllers\Integration\NotMockedYetController;
use App\Http\Controllers\Integration\SiteController;
use App\Http\Controllers\Integration\VoucherController;
use App\Http\Controllers\Integration\WifiBroadcastController;
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

Route::prefix('v1/sites/{siteId}')->group(function () {
    // Clients
    Route::get('clients', [ClientController::class, 'index'])->name('list-connected-clients');
    Route::get('clients/{clientId}', [ClientController::class, 'show'])->name('get-connected-client-details');
    Route::post('clients/{clientId}/actions', [ClientController::class, 'action'])->name('execute-client-action');

    // Networks
    Route::get('networks', [NetworkController::class, 'index'])->name('list-networks');
    Route::post('networks', [NetworkController::class, 'store'])->name('create-network');
    Route::delete('networks/{networkId}', [NetworkController::class, 'destroy'])->name('delete-network');
    Route::get('networks/{networkId}', [NetworkController::class, 'show'])->name('get-network-details');
    Route::put('networks/{networkId}', [NetworkController::class, 'update'])->name('update-network');
    Route::get('networks/{networkId}/references', [NetworkController::class, 'references'])->name('get-network-references');

    // WiFi Broadcasts
    Route::get('wifi/broadcasts', [WifiBroadcastController::class, 'index'])->name('list-wifi-broadcasts');
    Route::post('wifi/broadcasts', [WifiBroadcastController::class, 'store'])->name('create-wifi-broadcast');
    Route::delete('wifi/broadcasts/{wifiBroadcastId}', [WifiBroadcastController::class, 'destroy'])->name('delete-wifi-broadcast');
    Route::get('wifi/broadcasts/{wifiBroadcastId}', [WifiBroadcastController::class, 'show'])->name('get-wifi-broadcast-details');
    Route::put('wifi/broadcasts/{wifiBroadcastId}', [WifiBroadcastController::class, 'update'])->name('update-wifi-broadcast');

    // Hotspot
    Route::delete('hotspot/vouchers', [VoucherController::class, 'destroyMany'])->name('delete-vouchers');
    Route::get('hotspot/vouchers', [VoucherController::class, 'index'])->name('list-vouchers');
    Route::post('hotspot/vouchers', [VoucherController::class, 'store'])->name('generate-vouchers');
    Route::delete('hotspot/vouchers/{voucherId}', [VoucherController::class, 'destroy'])->name('delete-voucher');
    Route::get('hotspot/vouchers/{voucherId}', [VoucherController::class, 'show'])->name('get-voucher-details');
});

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

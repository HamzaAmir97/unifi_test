<?php

use App\Http\Controllers\Integration\AclRuleController;
use App\Http\Controllers\Integration\ClientController;
use App\Http\Controllers\Integration\DeviceController;
use App\Http\Controllers\Integration\DnsPolicyController;
use App\Http\Controllers\Integration\FirewallPolicyController;
use App\Http\Controllers\Integration\FirewallZoneController;
use App\Http\Controllers\Integration\InfoController;
use App\Http\Controllers\Integration\NetworkController;
use App\Http\Controllers\Integration\ReferenceController;
use App\Http\Controllers\Integration\SiteController;
use App\Http\Controllers\Integration\SwitchingController;
use App\Http\Controllers\Integration\TrafficMatchingListController;
use App\Http\Controllers\Integration\VoucherController;
use App\Http\Controllers\Integration\WifiBroadcastController;
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

// Global lists
Route::get('v1/pending-devices', [DeviceController::class, 'pending'])->name('list-devices-pending-adoption');
Route::get('v1/countries', [ReferenceController::class, 'countries'])->name('list-countries');
Route::get('v1/dpi/applications', [ReferenceController::class, 'dpiApplications'])->name('list-dpi-applications');
Route::get('v1/dpi/categories', [ReferenceController::class, 'dpiCategories'])->name('list-dpi-application-categories');

Route::prefix('v1/sites/{siteId}')->group(function () {
    // UniFi Devices
    Route::get('devices', [DeviceController::class, 'index'])->name('list-adopted-devices');
    Route::post('devices', [DeviceController::class, 'adopt'])->name('adopt-devices');
    Route::delete('devices/{deviceId}', [DeviceController::class, 'destroy'])->name('remove-unadopt-device');
    Route::get('devices/{deviceId}', [DeviceController::class, 'show'])->name('get-adopted-device-details');
    Route::post('devices/{deviceId}/actions', [DeviceController::class, 'action'])->name('execute-adopted-device-action');
    Route::post('devices/{deviceId}/interfaces/ports/{portIdx}/actions', [DeviceController::class, 'portAction'])->name('execute-port-action');
    Route::get('devices/{deviceId}/statistics/latest', [DeviceController::class, 'statistics'])->name('get-latest-adopted-device-statistics');

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

    // Firewall. T4: policies/ordering is declared BEFORE policies/{firewallPolicyId},
    // otherwise the word "ordering" is matched as a policy id.
    Route::get('firewall/policies', [FirewallPolicyController::class, 'index'])->name('list-firewall-policies');
    Route::post('firewall/policies', [FirewallPolicyController::class, 'store'])->name('create-firewall-policy');
    Route::get('firewall/policies/ordering', [FirewallPolicyController::class, 'ordering'])->name('get-user-defined-firewall-policy-ordering');
    Route::put('firewall/policies/ordering', [FirewallPolicyController::class, 'reorder'])->name('reorder-user-defined-firewall-policies');
    Route::delete('firewall/policies/{firewallPolicyId}', [FirewallPolicyController::class, 'destroy'])->name('delete-firewall-policy');
    Route::get('firewall/policies/{firewallPolicyId}', [FirewallPolicyController::class, 'show'])->name('get-firewall-policy');
    Route::patch('firewall/policies/{firewallPolicyId}', [FirewallPolicyController::class, 'patch'])->name('patch-firewall-policy');
    Route::put('firewall/policies/{firewallPolicyId}', [FirewallPolicyController::class, 'update'])->name('update-firewall-policy');
    Route::get('firewall/zones', [FirewallZoneController::class, 'index'])->name('list-firewall-zones');
    Route::post('firewall/zones', [FirewallZoneController::class, 'store'])->name('create-custom-firewall-zone');
    Route::delete('firewall/zones/{firewallZoneId}', [FirewallZoneController::class, 'destroy'])->name('delete-custom-firewall-zone');
    Route::get('firewall/zones/{firewallZoneId}', [FirewallZoneController::class, 'show'])->name('get-firewall-zone');
    Route::put('firewall/zones/{firewallZoneId}', [FirewallZoneController::class, 'update'])->name('update-firewall-zone');

    // Access Control (ACL Rules). acl-rules/ordering is declared BEFORE acl-rules/{aclRuleId}.
    Route::get('acl-rules', [AclRuleController::class, 'index'])->name('list-acl-rules');
    Route::post('acl-rules', [AclRuleController::class, 'store'])->name('create-acl-rule');
    Route::get('acl-rules/ordering', [AclRuleController::class, 'ordering'])->name('get-user-defined-acl-rule-ordering');
    Route::put('acl-rules/ordering', [AclRuleController::class, 'reorder'])->name('reorder-user-defined-acl-rules');
    Route::delete('acl-rules/{aclRuleId}', [AclRuleController::class, 'destroy'])->name('delete-acl-rule');
    Route::get('acl-rules/{aclRuleId}', [AclRuleController::class, 'show'])->name('get-acl-rule');
    Route::put('acl-rules/{aclRuleId}', [AclRuleController::class, 'update'])->name('update-acl-rule');

    // Switching (derived from the devices, read only)
    Route::get('switching/lags', [SwitchingController::class, 'lags'])->name('list-lags');
    Route::get('switching/lags/{lagId}', [SwitchingController::class, 'lag'])->name('get-lag-details');
    Route::get('switching/mc-lag-domains', [SwitchingController::class, 'mcLagDomains'])->name('list-mc-lag-domains');
    Route::get('switching/mc-lag-domains/{mcLagDomainId}', [SwitchingController::class, 'mcLagDomain'])->name('get-mc-lag-domain');
    Route::get('switching/switch-stacks', [SwitchingController::class, 'switchStacks'])->name('list-switch-stacks');
    Route::get('switching/switch-stacks/{switchStackId}', [SwitchingController::class, 'switchStack'])->name('get-switch-stack');

    // DNS Policies
    Route::get('dns/policies', [DnsPolicyController::class, 'index'])->name('list-dns-policies');
    Route::post('dns/policies', [DnsPolicyController::class, 'store'])->name('create-dns-policy');
    Route::delete('dns/policies/{dnsPolicyId}', [DnsPolicyController::class, 'destroy'])->name('delete-dns-policy');
    Route::get('dns/policies/{dnsPolicyId}', [DnsPolicyController::class, 'show'])->name('get-dns-policy');
    Route::put('dns/policies/{dnsPolicyId}', [DnsPolicyController::class, 'update'])->name('update-dns-policy');

    // Traffic Matching Lists
    Route::get('traffic-matching-lists', [TrafficMatchingListController::class, 'index'])->name('list-traffic-matching-lists');
    Route::post('traffic-matching-lists', [TrafficMatchingListController::class, 'store'])->name('create-traffic-matching-list');
    Route::delete('traffic-matching-lists/{trafficMatchingListId}', [TrafficMatchingListController::class, 'destroy'])->name('delete-traffic-matching-list');
    Route::get('traffic-matching-lists/{trafficMatchingListId}', [TrafficMatchingListController::class, 'show'])->name('get-traffic-matching-list');
    Route::put('traffic-matching-lists/{trafficMatchingListId}', [TrafficMatchingListController::class, 'update'])->name('update-traffic-matching-list');

    // Supporting Resources (site-scoped)
    Route::get('device-tags', [ReferenceController::class, 'deviceTags'])->name('list-device-tags');
    Route::get('radius/profiles', [ReferenceController::class, 'radiusProfiles'])->name('list-radius-profiles');
    Route::get('vpn/servers', [ReferenceController::class, 'vpnServers'])->name('list-vpn-servers');
    Route::get('vpn/site-to-site-tunnels', [ReferenceController::class, 'siteToSiteTunnels'])->name('list-site-to-site-vpn-tunnels');
    Route::get('wans', [ReferenceController::class, 'wans'])->name('list-wan-interfaces');
});

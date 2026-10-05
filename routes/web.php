<?php

use Illuminate\Support\Facades\Route;

/*
 * The only web route: a description of the mock. Integration and control routes live in
 * routes/integration.php and routes/control.php under the `api` group (R4) — never here,
 * because the web group's CSRF check answers 419 to every POST from Postman.
 */
Route::get('/', fn () => response()->json([
    'name' => 'UniFi Network API — local mock',
    'version' => config('unifi_mock.app_version'),
    'integrationBase' => url('v1/connector/consoles/'.config('unifi_mock.console_id').'/proxy/network/integration'),
    'controlBase' => url('__mock'),
    'swagger' => url('docs'),
    'docs' => 'README.md',
]));

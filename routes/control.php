<?php

use App\Http\Controllers\Control\ClockController;
use App\Http\Controllers\Control\RedeemController;
use Illuminate\Support\Facades\Route;

/*
 * The mock control plane, /__mock/* (contracts/control-api.md). Not part of UniFi:
 * no X-API-KEY, and 404 for everything when APP_ENV=production (FR-018).
 */
Route::post('hotspot/redeem', RedeemController::class)->name('mock.redeem');
Route::post('clock', ClockController::class)->name('mock.clock');

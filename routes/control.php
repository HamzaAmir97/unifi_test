<?php

use App\Http\Controllers\Control\ClockController;
use App\Http\Controllers\Control\FaultController;
use App\Http\Controllers\Control\RedeemController;
use App\Http\Controllers\Control\RequestLogController;
use App\Http\Controllers\Control\SettingsController;
use App\Http\Controllers\Control\SimulationController;
use App\Http\Controllers\Control\StateController;
use Illuminate\Support\Facades\Route;

/*
 * The mock control plane, /__mock/* (contracts/control-api.md). Not part of UniFi:
 * no X-API-KEY, and 404 for everything when APP_ENV=production (FR-018).
 */
Route::post('reset', [StateController::class, 'reset'])->name('mock.reset');
Route::get('state', [StateController::class, 'show'])->name('mock.state');
Route::post('hotspot/redeem', RedeemController::class)->name('mock.redeem');
Route::post('clients/connect', [SimulationController::class, 'connect'])->name('mock.clients.connect');
Route::post('clients/disconnect', [SimulationController::class, 'disconnect'])->name('mock.clients.disconnect');
Route::post('devices/pending', [SimulationController::class, 'pendingDevice'])->name('mock.devices.pending');
Route::post('clock', ClockController::class)->name('mock.clock');
Route::get('faults', [FaultController::class, 'index'])->name('mock.faults');
Route::post('faults', [FaultController::class, 'store'])->name('mock.faults.store');
Route::delete('faults', [FaultController::class, 'destroy'])->name('mock.faults.clear');
Route::get('requests', [RequestLogController::class, 'index'])->name('mock.requests');
Route::delete('requests', [RequestLogController::class, 'destroy'])->name('mock.requests.clear');
Route::post('settings', SettingsController::class)->name('mock.settings');

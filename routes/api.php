<?php

use App\Http\Controllers\Api\EspController;
use App\Http\Controllers\Api\KontrolMesinController;
use App\Http\Middleware\CatatKoneksiEsp;
use Illuminate\Support\Facades\Route;

// Semua request dari ESP32 dicatat koneksinya lewat header X-ESP-ID
Route::middleware(CatatKoneksiEsp::class)->group(function () {
    Route::post('esp/ping', [EspController::class, 'ping']);
    Route::post('esp/status', [EspController::class, 'status']);

    Route::prefix('kontrol-mesin')->group(function () {
        Route::get('/', [KontrolMesinController::class, 'index']);

        Route::post('{nomor}/on', [KontrolMesinController::class, 'on'])->whereNumber('nomor');
        Route::post('{nomor}/off', [KontrolMesinController::class, 'off'])->whereNumber('nomor');
        Route::post('{nomor}/toggle', [KontrolMesinController::class, 'toggle'])->whereNumber('nomor');
    });
});

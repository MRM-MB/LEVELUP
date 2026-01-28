<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PicoDisplayController;

return function () {
    // Pico W display API endpoints (no auth required - internal hardware access)
    Route::get('/api/pico/display', [PicoDisplayController::class, 'getState'])
        ->name('api.pico.display');

    Route::post('/api/pico/timer-phase', [PicoDisplayController::class, 'updateTimerPhase'])
        ->name('api.pico.timer-phase');

    Route::post('/api/pico/timer-pause', [PicoDisplayController::class, 'toggleTimerPause'])
        ->name('api.pico.timer-pause');
};

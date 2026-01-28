<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

return function () {
    // Authentication Routes (guest-only)
    Route::middleware('guest')->group(function () {
        // Login
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'authenticate'])->name('login.perform');
    });

    // Logout (only accessible when authenticated)
    Route::post('/logout', [LoginController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');

    // Password Reset Routes (placeholders for future implementation)
    Route::get('/forgot-password', function () {
        return redirect()->route('login');
    })->name('password.request');
};

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminRewardsController;
use App\Http\Controllers\Admin\AdminDeskController;
use App\Http\Middleware\IsAdmin;

return function () {
    Route::middleware(['auth', IsAdmin::class])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

            // Users
            Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
            Route::patch('/users/{user}/promote', [AdminUserController::class, 'promote'])->name('users.promote');
            Route::patch('/users/{user}/demote', [AdminUserController::class, 'demote'])->name('users.demote');
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

            // Rewards
            Route::post('/rewards', [AdminRewardsController::class, 'store'])->name('rewards.store');
            Route::put('/rewards/{reward}', [AdminRewardsController::class, 'update'])->name('rewards.update');
            Route::patch('/rewards/{reward}/archive', [AdminRewardsController::class, 'archive'])->name('rewards.archive');
            Route::patch('/rewards/{reward}/unarchive', [AdminRewardsController::class, 'unarchive'])->name('rewards.unarchive');
            Route::delete('/rewards/{reward}', [AdminRewardsController::class, 'destroy'])->name('rewards.destroy');

            // Desks
            Route::get('/desks', [AdminDeskController::class, 'index'])->name('desks.index');
            Route::post('/desks', [AdminDeskController::class, 'store'])->name('desks.store');
            Route::patch('/desks/{desk}', [AdminDeskController::class, 'update'])->name('desks.update');
            Route::delete('/desks/{desk}', [AdminDeskController::class, 'destroy'])->name('desks.destroy');
            Route::post('/desks/bulk-height', [AdminDeskController::class, 'bulkHeight'])->name('desks.bulk-height');
        });
};

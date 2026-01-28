<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\HealthCycleController;
use App\Http\Controllers\RewardsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DeskSimulatorController;
use App\Http\Middleware\IsAdmin;

return function () {

    // Protected Routes (require authentication)
    Route::middleware('auth')->group(function () {

        // Profile Routes
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

        // Statistics Routes
        Route::get('/statistics', [StatisticsController::class, 'statistics'])->name('statistics');

        // Rewards Routes
        Route::get('/rewards', [RewardsController::class, 'index'])->name('rewards.index');
        Route::post('/rewards/toggle-save', [RewardsController::class, 'toggleSave'])->name('rewards.toggleSave');
        Route::get('/rewards/saved', [RewardsController::class, 'getSavedRewards'])->name('rewards.getSaved');
        Route::post('/rewards/redeem', [RewardsController::class, 'redeem'])->name('rewards.redeem');

        // Desks simulator Routes
        Route::prefix('api/simulator')->name('simulator.')->group(function () {
            Route::get('desks', [DeskSimulatorController::class, 'index'])->name('desks.index');
            Route::get('desks/{desk}', [DeskSimulatorController::class, 'show'])->name('desks.show');
            Route::get('desks/{desk}/{category}', [DeskSimulatorController::class, 'showCategory'])->name('desks.category');
            Route::put('desks/{desk}/state', [DeskSimulatorController::class, 'updateState'])->name('desks.state');
            Route::post('desks/{desk}/sit', [DeskSimulatorController::class, 'moveToSit'])->name('desks.sit');
            Route::post('desks/{desk}/stand', [DeskSimulatorController::class, 'moveToStand'])->name('desks.stand');
        });

        // Admin Rewards Management Routes (admin only, still inside auth)
        Route::middleware(IsAdmin::class)
            ->prefix('admin/rewards')
            ->name('rewards.')
            ->group(function () {
                Route::get('create', [RewardsController::class, 'create'])->name('create');
                Route::post('store', [RewardsController::class, 'store'])->name('store');
                Route::get('{reward}/edit', [RewardsController::class, 'edit'])->name('edit');
                Route::put('{reward}', [RewardsController::class, 'update'])->name('update');
                Route::delete('{reward}', [RewardsController::class, 'destroy'])->name('destroy');
            });

        // Health Cycle API routes (require authentication)
        Route::prefix('api/health-cycle')->group(function () {
            Route::post('/complete', [HealthCycleController::class, 'completeHealthCycle']);
            Route::get('/points-status', [HealthCycleController::class, 'getPointsStatus']);
            Route::get('/history', [HealthCycleController::class, 'getHistory']);
        });

        // Statistics API routes (require authentication)
        Route::prefix('api/statistics')->group(function () {
            Route::get('/today-stats', [StatisticsController::class, 'getTodayStats']);
            Route::get('/all-time-stats', [StatisticsController::class, 'getAllTimeStats']);
        });
    });
};

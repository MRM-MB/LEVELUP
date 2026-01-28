<?php

use Illuminate\Support\Facades\Route;

return function () {
    // Home route
    Route::get('/', function () {
        return view('home');
    })->name('home');
};

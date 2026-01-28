<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here only the route mapping functions from the smaller route files
| are loaded and executed.
|
*/

(require __DIR__ . '/web/home.php')();
(require __DIR__ . '/web/pico.php')();
(require __DIR__ . '/web/auth.php')();
(require __DIR__ . '/web/app.php')();
(require __DIR__ . '/web/admin.php')();

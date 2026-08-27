<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LaraDb\Http\Controllers\LaraDbController;

/*
|--------------------------------------------------------------------------
| LaraDb routes
|--------------------------------------------------------------------------
|
| Loaded by LaraDbServiceProvider, inside a group that applies the configured
| prefix and middleware. Both routes are GET only: the package never writes.
|
*/

Route::get('/', [LaraDbController::class, 'index'])->name('index');

Route::get('/tables/{table}', [LaraDbController::class, 'show'])
    ->where('table', '[^/]+')
    ->name('table');

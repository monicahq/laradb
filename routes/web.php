<?php

use Illuminate\Support\Facades\Route;
use LaraDb\Controllers\DBController;

Route::prefix('db')->group(function () {
    Route::get('', [DBController::class, 'index'])->name('db.index');
});

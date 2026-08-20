<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

/*
|--------------------------------------------------------------------------
| TradeX Admin Routes
|--------------------------------------------------------------------------
*/

require __DIR__ . '/admin.php';
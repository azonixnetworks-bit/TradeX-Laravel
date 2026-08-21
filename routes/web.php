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

/*
|--------------------------------------------------------------------------
| ADMIN-03B — Protected Admin HLS Preview Routes
|--------------------------------------------------------------------------
|
| Kept in a separate route file so the frozen ADMIN-03 route inventory is
| not rewritten merely to add the protected media-preview layer.
|
*/

require __DIR__ . '/admin_video_playback.php';

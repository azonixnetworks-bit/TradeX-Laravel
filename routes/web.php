<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

require __DIR__ . '/admin.php';
require __DIR__ . '/admin_video_playback.php';
require __DIR__ . '/admin_our_channel.php';

/*
|--------------------------------------------------------------------------
| ADMIN-05 — Trade Ideas Management
|--------------------------------------------------------------------------
|
| Core Trade Idea setup, targets and audited lifecycle.
| Telegram / WhatsApp distribution is reserved for ADMIN-05A.
|
*/

require __DIR__ . '/admin_trade_ideas.php';

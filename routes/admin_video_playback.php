<?php

use App\Http\Controllers\Admin\Education\VideoPlaybackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ADMIN-03B — Protected HLS Preview / Media Gateway
|--------------------------------------------------------------------------
|
| These routes are intentionally isolated from routes/admin.php so the
| frozen ADMIN-03 / ADMIN-03A CRUD route contract is not disturbed.
|
| Every request remains behind admin.auth. The controller independently
| enforces education.view and nested Course -> Module -> Lesson -> Video
| ownership as defence-in-depth.
|
*/

Route::prefix('admin')
    ->name('admin.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::prefix('education')
            ->name('education.')
            ->group(function (): void {
                Route::get(
                    '/courses/{course}/modules/{module}/lessons/{lesson}/videos/{video}/preview',
                    [VideoPlaybackController::class, 'preview']
                )
                    ->scopeBindings()
                    ->whereNumber('course')
                    ->whereNumber('module')
                    ->whereNumber('lesson')
                    ->whereNumber('video')
                    ->name('courses.modules.lessons.videos.preview');

                Route::get(
                    '/courses/{course}/modules/{module}/lessons/{lesson}/videos/{video}/media/{path}',
                    [VideoPlaybackController::class, 'media']
                )
                    ->scopeBindings()
                    ->whereNumber('course')
                    ->whereNumber('module')
                    ->whereNumber('lesson')
                    ->whereNumber('video')
                    ->where('path', '.*')
                    ->name('courses.modules.lessons.videos.media');
            });
    });

<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Users\UserController;
use App\Http\Controllers\Admin\Users\UserDeviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Public Admin Routes
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/login',
            [LoginController::class, 'show']
        )->name('login');

        Route::post(
            '/login',
            [LoginController::class, 'login']
        )->name('login.submit');


        /*
        |--------------------------------------------------------------------------
        | Protected Admin Routes
        |--------------------------------------------------------------------------
        */

        Route::middleware('admin.auth')
            ->group(function () {

                /*
                |--------------------------------------------------------------------------
                | Admin Root
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/',
                    fn () =>
                        redirect()->route(
                            'admin.dashboard'
                        )
                );


                /*
                |--------------------------------------------------------------------------
                | Dashboard
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/dashboard',
                    [DashboardController::class, 'index']
                )->name('dashboard');


                /*
                |--------------------------------------------------------------------------
                | Logout
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/logout',
                    [LoginController::class, 'logout']
                )->name('logout');


                /*
                |--------------------------------------------------------------------------
                | User Management
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/users',
                    [UserController::class, 'index']
                )->name('users.index');


                Route::patch(
                    '/users/{user}/status',
                    [UserController::class, 'updateStatus']
                )
                    ->whereNumber('user')
                    ->name('users.status.update');


                Route::get(
                    '/users/{user}',
                    [UserController::class, 'show']
                )
                    ->whereNumber('user')
                    ->name('users.show');


                /*
                |--------------------------------------------------------------------------
                | User Device Security
                |--------------------------------------------------------------------------
                |
                | SECURITY:
                |
                | scopeBindings() guarantees that {device} must be resolved
                | through the parent user's devices() relationship.
                |
                | Therefore:
                |
                | /users/10/devices/55/revoke
                |
                | will return 404 when device #55 does not belong to user #10.
                |
                | The controller-level ownership check is intentionally kept
                | as defence-in-depth.
                |
                */

                Route::patch(
                    '/users/{user}/devices/{device}/revoke',
                    [UserDeviceController::class, 'revoke']
                )
                    ->scopeBindings()
                    ->whereNumber('user')
                    ->whereNumber('device')
                    ->name('users.devices.revoke');


                /*
                |--------------------------------------------------------------------------
                | Reset User Device Slots
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/users/{user}/devices/reset',
                    [UserDeviceController::class, 'reset']
                )
                    ->whereNumber('user')
                    ->name('users.devices.reset');


                /*
                |--------------------------------------------------------------------------
                | Revoke User Refresh Sessions
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/users/{user}/sessions/revoke',
                    [UserDeviceController::class, 'revokeSessions']
                )
                    ->whereNumber('user')
                    ->name('users.sessions.revoke');
            });
    });
<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Education\CourseController;
use App\Http\Controllers\Admin\Education\ModuleController;
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


                /*
                |--------------------------------------------------------------------------
                | ADMIN-03 — Education / Course Management
                |--------------------------------------------------------------------------
                |
                | All Education routes remain inside the existing admin.auth
                | boundary. Exact education.* permissions are enforced again
                | inside Form Requests and CourseController methods.
                |
                | Route model identifiers are numeric and constrained here for
                | consistency with the frozen ADMIN-02E admin route contract.
                |
                */

                Route::prefix('education')
                    ->name('education.')
                    ->group(function () {

                        /*
                        |--------------------------------------------------------------------------
                        | Course Collection
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            '/courses',
                            [CourseController::class, 'index']
                        )->name('courses.index');


                        Route::get(
                            '/courses/create',
                            [CourseController::class, 'create']
                        )->name('courses.create');


                        Route::post(
                            '/courses',
                            [CourseController::class, 'store']
                        )->name('courses.store');


                        /*
                        |--------------------------------------------------------------------------
                        | Course Publication
                        |--------------------------------------------------------------------------
                        |
                        | Publication is intentionally a dedicated PATCH action.
                        | It is NOT merged into the normal update endpoint.
                        |
                        | This preserves the separate education.publish security
                        | boundary established by ADMIN-03.
                        |
                        */

                        Route::patch(
                            '/courses/{course}/publication',
                            [CourseController::class, 'publication']
                        )
                            ->whereNumber('course')
                            ->name('courses.publication');


                        /*
                        |--------------------------------------------------------------------------
                        | ADMIN-03 — Course Module Management
                        |--------------------------------------------------------------------------
                        |
                        | Modules are always addressed inside their parent Course:
                        |
                        | /education/courses/{course}/modules/...
                        |
                        | Nested member routes use scopeBindings() so Laravel must
                        | resolve {module} through Course::modules().
                        |
                        | ModuleController keeps an explicit ownership check as a
                        | second IDOR defence boundary.
                        |
                        */

                        Route::get(
                            '/courses/{course}/modules',
                            [ModuleController::class, 'index']
                        )
                            ->whereNumber('course')
                            ->name('courses.modules.index');


                        Route::get(
                            '/courses/{course}/modules/create',
                            [ModuleController::class, 'create']
                        )
                            ->whereNumber('course')
                            ->name('courses.modules.create');


                        Route::post(
                            '/courses/{course}/modules',
                            [ModuleController::class, 'store']
                        )
                            ->whereNumber('course')
                            ->name('courses.modules.store');


                        /*
                        |--------------------------------------------------------------------------
                        | Module Publication
                        |--------------------------------------------------------------------------
                        |
                        | Publication remains separate from normal metadata update
                        | and is protected by education.publish in the request and
                        | controller layers.
                        |
                        */

                        Route::patch(
                            '/courses/{course}/modules/{module}/publication',
                            [ModuleController::class, 'publication']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->name('courses.modules.publication');


                        /*
                        |--------------------------------------------------------------------------
                        | Module Member Routes
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            '/courses/{course}/modules/{module}/edit',
                            [ModuleController::class, 'edit']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->name('courses.modules.edit');


                        Route::patch(
                            '/courses/{course}/modules/{module}',
                            [ModuleController::class, 'update']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->name('courses.modules.update');


                        Route::delete(
                            '/courses/{course}/modules/{module}',
                            [ModuleController::class, 'destroy']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->name('courses.modules.destroy');


                        /*
                        |--------------------------------------------------------------------------
                        | Course Member Routes
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            '/courses/{course}/edit',
                            [CourseController::class, 'edit']
                        )
                            ->whereNumber('course')
                            ->name('courses.edit');


                        Route::patch(
                            '/courses/{course}',
                            [CourseController::class, 'update']
                        )
                            ->whereNumber('course')
                            ->name('courses.update');


                        Route::delete(
                            '/courses/{course}',
                            [CourseController::class, 'destroy']
                        )
                            ->whereNumber('course')
                            ->name('courses.destroy');


                        Route::get(
                            '/courses/{course}',
                            [CourseController::class, 'show']
                        )
                            ->whereNumber('course')
                            ->name('courses.show');
                    });
            });
    });
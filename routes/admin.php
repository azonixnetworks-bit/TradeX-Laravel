<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Education\AssignmentController;
use App\Http\Controllers\Admin\Education\CourseController;
use App\Http\Controllers\Admin\Education\LessonController;
use App\Http\Controllers\Admin\Education\ModuleController;
use App\Http\Controllers\Admin\Education\VideoController;
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
                | inside Form Requests and Education controllers.
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
                        | ADMIN-03 — Lesson Management
                        |--------------------------------------------------------------------------
                        |
                        | Lessons are always addressed inside both parent Course
                        | and Course Module:
                        |
                        | /education/courses/{course}/modules/{module}/lessons/...
                        |
                        | Triple nested member routes use scopeBindings() so:
                        |
                        | {module} must belong to {course}
                        | {lesson} must belong to {module}
                        |
                        | LessonController repeats both ownership checks as
                        | defence-in-depth against future route regressions.
                        |
                        */

                        Route::get(
                            '/courses/{course}/modules/{module}/lessons',
                            [LessonController::class, 'index']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->name('courses.modules.lessons.index');


                        Route::get(
                            '/courses/{course}/modules/{module}/lessons/create',
                            [LessonController::class, 'create']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->name('courses.modules.lessons.create');


                        Route::post(
                            '/courses/{course}/modules/{module}/lessons',
                            [LessonController::class, 'store']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->name('courses.modules.lessons.store');


                        /*
                        |--------------------------------------------------------------------------
                        | Lesson Publication
                        |--------------------------------------------------------------------------
                        |
                        | Publication remains separate from normal lesson metadata
                        | update and is protected by education.publish in both the
                        | Form Request and LessonController.
                        |
                        */

                        Route::patch(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/publication',
                            [LessonController::class, 'publication']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->name('courses.modules.lessons.publication');


                        /*
                        |--------------------------------------------------------------------------
                        | Lesson Member Routes
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/edit',
                            [LessonController::class, 'edit']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->name('courses.modules.lessons.edit');


                        Route::patch(
                            '/courses/{course}/modules/{module}/lessons/{lesson}',
                            [LessonController::class, 'update']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->name('courses.modules.lessons.update');


                        Route::delete(
                            '/courses/{course}/modules/{module}/lessons/{lesson}',
                            [LessonController::class, 'destroy']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->name('courses.modules.lessons.destroy');



                        /*
                        |--------------------------------------------------------------------------
                        | ADMIN-03 — Protected Lesson Video Management
                        |--------------------------------------------------------------------------
                        |
                        | Videos are nested through Course -> Module -> Lesson.
                        | All routes use scoped bindings so a video cannot be
                        | addressed through a lesson that does not own it.
                        |
                        */

                        Route::get(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/videos',
                            [VideoController::class, 'index']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->name('courses.modules.lessons.videos.index');


                        Route::get(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/videos/create',
                            [VideoController::class, 'create']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->name('courses.modules.lessons.videos.create');


                        Route::post(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/videos',
                            [VideoController::class, 'store']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->name('courses.modules.lessons.videos.store');


                        Route::patch(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/videos/{video}/publication',
                            [VideoController::class, 'publication']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->whereNumber('video')
                            ->name('courses.modules.lessons.videos.publication');


                        Route::get(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/videos/{video}/edit',
                            [VideoController::class, 'edit']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->whereNumber('video')
                            ->name('courses.modules.lessons.videos.edit');

                        Route::get(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/videos/{video}/processing-status',
                            [VideoController::class, 'processingStatus']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->whereNumber('video')
                            ->name('courses.modules.lessons.videos.processing-status');


                        Route::patch(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/videos/{video}',
                            [VideoController::class, 'update']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->whereNumber('video')
                            ->name('courses.modules.lessons.videos.update');


                        Route::delete(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/videos/{video}',
                            [VideoController::class, 'destroy']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->whereNumber('video')
                            ->name('courses.modules.lessons.videos.destroy');


                        /*
                        |--------------------------------------------------------------------------
                        | ADMIN-03 — Assignment Management
                        |--------------------------------------------------------------------------
                        |
                        | Assignment instructions belong to a Lesson. Learner
                        | assignment files are not uploaded through TradeX.
                        | Submission remains external through the approved
                        | WhatsApp flow or NONE.
                        |
                        */

                        Route::get(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/assignments',
                            [AssignmentController::class, 'index']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->name('courses.modules.lessons.assignments.index');


                        Route::get(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/assignments/create',
                            [AssignmentController::class, 'create']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->name('courses.modules.lessons.assignments.create');


                        Route::post(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/assignments',
                            [AssignmentController::class, 'store']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->name('courses.modules.lessons.assignments.store');


                        Route::patch(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/assignments/{assignment}/publication',
                            [AssignmentController::class, 'publication']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->whereNumber('assignment')
                            ->name('courses.modules.lessons.assignments.publication');


                        Route::get(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/assignments/{assignment}/edit',
                            [AssignmentController::class, 'edit']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->whereNumber('assignment')
                            ->name('courses.modules.lessons.assignments.edit');


                        Route::patch(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/assignments/{assignment}',
                            [AssignmentController::class, 'update']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->whereNumber('assignment')
                            ->name('courses.modules.lessons.assignments.update');


                        Route::delete(
                            '/courses/{course}/modules/{module}/lessons/{lesson}/assignments/{assignment}',
                            [AssignmentController::class, 'destroy']
                        )
                            ->scopeBindings()
                            ->whereNumber('course')
                            ->whereNumber('module')
                            ->whereNumber('lesson')
                            ->whereNumber('assignment')
                            ->name('courses.modules.lessons.assignments.destroy');


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

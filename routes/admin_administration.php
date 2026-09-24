<?php

use App\Http\Controllers\Admin\Administration\AdminDeviceController;
use App\Http\Controllers\Admin\Administration\AdminUserController;
use App\Http\Controllers\Admin\Administration\PermissionController;
use App\Http\Controllers\Admin\Administration\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/administration')
    ->name('admin.administration.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::prefix('admins')
            ->name('admins.')
            ->group(function (): void {
                Route::get('/{adminUser}/devices', [AdminDeviceController::class, 'index'])
                    ->whereNumber('adminUser')->middleware('throttle:60,1')->name('devices.index');
                Route::patch('/{adminUser}/devices/{adminDevice}', [AdminDeviceController::class, 'update'])
                    ->whereNumber(['adminUser', 'adminDevice'])->scopeBindings()->middleware('throttle:10,1')->name('devices.update');
                Route::delete('/{adminUser}/devices/{adminDevice}', [AdminDeviceController::class, 'destroy'])
                    ->whereNumber(['adminUser', 'adminDevice'])->scopeBindings()->middleware('throttle:10,1')->name('devices.destroy');
                Route::get(
                    '/',
                    [AdminUserController::class, 'index']
                )
                    ->middleware('throttle:60,1')
                    ->name('index');

                Route::get(
                    '/create',
                    [AdminUserController::class, 'create']
                )
                    ->middleware('throttle:30,1')
                    ->name('create');

                Route::post(
                    '/',
                    [AdminUserController::class, 'store']
                )
                    ->middleware('throttle:10,1')
                    ->name('store');

                Route::get(
                    '/{adminUser}/edit',
                    [AdminUserController::class, 'edit']
                )
                    ->whereNumber('adminUser')
                    ->middleware('throttle:60,1')
                    ->name('edit');

                Route::patch(
                    '/{adminUser}',
                    [AdminUserController::class, 'update']
                )
                    ->whereNumber('adminUser')
                    ->middleware('throttle:20,1')
                    ->name('update');

                Route::patch(
                    '/{adminUser}/status',
                    [AdminUserController::class, 'updateStatus']
                )
                    ->whereNumber('adminUser')
                    ->middleware('throttle:10,1')
                    ->name('status');

                Route::patch(
                    '/{adminUser}/roles',
                    [AdminUserController::class, 'syncRoles']
                )
                    ->whereNumber('adminUser')
                    ->middleware('throttle:10,1')
                    ->name('roles');
            });

        Route::prefix('roles')
            ->name('roles.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [RoleController::class, 'index']
                )
                    ->middleware('throttle:60,1')
                    ->name('index');

                Route::get(
                    '/create',
                    [RoleController::class, 'create']
                )
                    ->middleware('throttle:30,1')
                    ->name('create');

                Route::post(
                    '/',
                    [RoleController::class, 'store']
                )
                    ->middleware('throttle:10,1')
                    ->name('store');

                Route::get(
                    '/{adminRole}/permissions',
                    [RoleController::class, 'permissions']
                )
                    ->whereNumber('adminRole')
                    ->middleware('throttle:60,1')
                    ->name('permissions');

                Route::patch(
                    '/{adminRole}',
                    [RoleController::class, 'update']
                )
                    ->whereNumber('adminRole')
                    ->middleware('throttle:20,1')
                    ->name('update');

                Route::patch(
                    '/{adminRole}/status',
                    [RoleController::class, 'updateStatus']
                )
                    ->whereNumber('adminRole')
                    ->middleware('throttle:10,1')
                    ->name('status');

                Route::patch(
                    '/{adminRole}/permissions',
                    [RoleController::class, 'syncPermissions']
                )
                    ->whereNumber('adminRole')
                    ->middleware('throttle:10,1')
                    ->name('permissions.update');
            });

        Route::get(
            '/permissions',
            [PermissionController::class, 'index']
        )
            ->middleware('throttle:60,1')
            ->name('permissions.index');
    });

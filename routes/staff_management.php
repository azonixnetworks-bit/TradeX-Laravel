<?php

use App\Http\Controllers\Admin\Staff\StaffMemberController;
use App\Http\Controllers\Staff\StaffPortalController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/staff')->name('admin.staff.')->middleware('admin.auth')->group(function (): void {
    Route::get('/', [StaffMemberController::class, 'index'])->name('index');
    Route::get('/create', [StaffMemberController::class, 'create'])->name('create');
    Route::post('/', [StaffMemberController::class, 'store'])->middleware('throttle:10,1')->name('store');
});

Route::prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/login', [StaffPortalController::class, 'showLogin'])->name('login');
    Route::post('/login', [StaffPortalController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    Route::middleware('staff.auth')->group(function (): void {
        Route::get('/', [StaffPortalController::class, 'dashboard'])->name('dashboard');
        Route::post('/leave', [StaffPortalController::class, 'storeLeave'])->middleware('throttle:10,1')->name('leave.store');
        Route::post('/logout', [StaffPortalController::class, 'logout'])->name('logout');
    });
});

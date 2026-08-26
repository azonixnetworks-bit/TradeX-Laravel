<?php

use App\Http\Controllers\Admin\Audit\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/audit')
    ->name('admin.audit.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get('/', [AuditLogController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('index');

        Route::get('/{auditLog}', [AuditLogController::class, 'show'])
            ->whereNumber('auditLog')
            ->middleware('throttle:60,1')
            ->name('show');
    });

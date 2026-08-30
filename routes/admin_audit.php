<?php

use App\Http\Controllers\Admin\Audit\AuditLogController;
use App\Http\Controllers\Admin\Audit\AuditReportController;
use Illuminate\Support\Facades\Route;

/*
 * ADMIN-12 audit browsing contract stays frozen at exactly two /admin/audit
 * GET routes. Report download lives under the separate /admin/reports path.
 */
Route::get(
    'admin/reports/audit/export',
    [AuditReportController::class, 'export']
)
    ->middleware([
        'admin.auth',
        'throttle:20,1',
    ])
    ->name('admin.audit.export');

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

<?php

use App\Http\Controllers\Admin\TradingFloor\TradingFloorAccessCredentialController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorAccessDeviceController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorAccessMonitorController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorAccessOverrideController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorAccessSettingsController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorClientController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorClientDocumentController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorCommissionInvoiceController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorCommissionPaymentController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorCredentialReplacementController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorDashboardController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorDocumentController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorReminderController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/trading-floor')
    ->name('admin.trading_floor.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get('/', [TradingFloorDashboardController::class, 'index'])->name('index');

        Route::get('/clients', [TradingFloorClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/create', [TradingFloorClientController::class, 'create'])->name('clients.create');
        Route::post('/clients', [TradingFloorClientController::class, 'store'])->middleware('throttle:20,1')->name('clients.store');
        Route::get('/clients/{client}', [TradingFloorClientController::class, 'show'])->whereUuid('client')->name('clients.show');
        Route::get('/clients/{client}/edit', [TradingFloorClientController::class, 'edit'])->whereUuid('client')->name('clients.edit');
        Route::patch('/clients/{client}', [TradingFloorClientController::class, 'update'])->whereUuid('client')->middleware('throttle:30,1')->name('clients.update');

        Route::post('/clients/{client}/credentials', [TradingFloorAccessCredentialController::class, 'store'])
            ->whereUuid('client')->middleware('throttle:20,1')->name('clients.credentials.store');
        Route::patch('/clients/{client}/credentials/{credential}/revoke', [TradingFloorAccessCredentialController::class, 'revoke'])
            ->whereUuid('client')->whereUuid('credential')->middleware('throttle:20,1')->name('clients.credentials.revoke');
        Route::post('/clients/{client}/credentials/{credential}/replace', [TradingFloorCredentialReplacementController::class, 'store'])
            ->whereUuid('client')->whereUuid('credential')->middleware('throttle:10,1')->name('clients.credentials.replace');
        Route::patch('/clients/{client}/access-override', [TradingFloorAccessOverrideController::class, 'update'])
            ->whereUuid('client')->middleware('throttle:20,1')->name('clients.access_override.update');

        Route::post('/clients/{client}/documents', [TradingFloorClientDocumentController::class, 'store'])
            ->whereUuid('client')->middleware('throttle:10,1')->name('clients.documents.store');
        Route::get('/clients/{client}/documents/{document}', [TradingFloorClientDocumentController::class, 'download'])
            ->whereUuid('client')->whereUuid('document')->name('clients.documents.download');
        Route::patch('/clients/{client}/documents/{document}/archive', [TradingFloorClientDocumentController::class, 'archive'])
            ->whereUuid('client')->whereUuid('document')->middleware('throttle:10,1')->name('clients.documents.archive');

        Route::get('/access', [TradingFloorAccessMonitorController::class, 'index'])->name('access.index');
        Route::get('/access/settings', [TradingFloorAccessSettingsController::class, 'index'])->name('access.settings');
        Route::patch('/access/settings', [TradingFloorAccessSettingsController::class, 'update'])->middleware('throttle:20,1')->name('access.settings.update');
        Route::post('/access/devices', [TradingFloorAccessDeviceController::class, 'store'])->middleware('throttle:10,1')->name('access.devices.store');
        Route::patch('/access/devices/{device}/rotate', [TradingFloorAccessDeviceController::class, 'rotate'])->whereUuid('device')->middleware('throttle:10,1')->name('access.devices.rotate');
        Route::patch('/access/devices/{device}/revoke', [TradingFloorAccessDeviceController::class, 'revoke'])->whereUuid('device')->middleware('throttle:10,1')->name('access.devices.revoke');

        Route::get('/commissions', [TradingFloorCommissionInvoiceController::class, 'index'])->name('commissions.index');
        Route::get('/clients/{client}/commissions/create', [TradingFloorCommissionInvoiceController::class, 'create'])->whereUuid('client')->name('commissions.create');
        Route::post('/clients/{client}/commissions', [TradingFloorCommissionInvoiceController::class, 'store'])->whereUuid('client')->middleware('throttle:20,1')->name('commissions.store');
        Route::get('/commissions/{invoice}', [TradingFloorCommissionInvoiceController::class, 'show'])->whereUuid('invoice')->name('commissions.show');
        Route::post('/commissions/{invoice}/payments', [TradingFloorCommissionPaymentController::class, 'store'])->whereUuid('invoice')->middleware('throttle:20,1')->name('commissions.payments.store');
        Route::patch('/commissions/{invoice}/payments/{payment}/reverse', [TradingFloorCommissionPaymentController::class, 'reverse'])
            ->whereUuid('invoice')->whereUuid('payment')->middleware('throttle:10,1')->name('commissions.payments.reverse');
        Route::get('/commissions/{invoice}/payments/{payment}/proof', [TradingFloorCommissionPaymentController::class, 'proof'])
            ->whereUuid('invoice')->whereUuid('payment')->name('commissions.payments.proof');

        Route::get('/reports', [TradingFloorReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/attendance.csv', [TradingFloorReportController::class, 'attendanceCsv'])->name('reports.attendance_csv');
        Route::get('/reports/finance.csv', [TradingFloorReportController::class, 'financeCsv'])->name('reports.finance_csv');
        Route::get('/clients/{client}/statement', [TradingFloorReportController::class, 'clientStatement'])
            ->whereUuid('client')->name('reports.client_statement');

        Route::get('/commissions/{invoice}/print', [TradingFloorDocumentController::class, 'invoice'])
            ->whereUuid('invoice')->name('documents.invoice');
        Route::get('/commissions/{invoice}/payments/{payment}/receipt', [TradingFloorDocumentController::class, 'receipt'])
            ->whereUuid('invoice')->whereUuid('payment')->name('documents.receipt');

        Route::get('/reminders', [TradingFloorReminderController::class, 'index'])->name('reminders.index');
        Route::post('/reminders/generate', [TradingFloorReminderController::class, 'generate'])
            ->middleware('throttle:6,1')->name('reminders.generate');
    });

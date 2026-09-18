<?php

use App\Http\Controllers\Admin\TradingFloor\TradingFloorMembershipController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorMembershipDocumentController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorMembershipPaymentController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorMembershipReportController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorResourceController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/trading-floor')
    ->name('admin.trading_floor.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get('/resources', [TradingFloorResourceController::class, 'index'])->name('resources.index');

        Route::get('/floors', [TradingFloorResourceController::class, 'floors'])->name('floors.index');
        Route::post('/floors', [TradingFloorResourceController::class, 'storeFloor'])->middleware('throttle:20,1')->name('floors.store');
        Route::patch('/floors/{floor}', [TradingFloorResourceController::class, 'updateFloor'])->whereUuid('floor')->middleware('throttle:30,1')->name('floors.update');

        Route::get('/desks', [TradingFloorResourceController::class, 'desks'])->name('desks.index');
        Route::post('/desks', [TradingFloorResourceController::class, 'storeDesk'])->middleware('throttle:30,1')->name('desks.store');
        Route::patch('/desks/{desk}', [TradingFloorResourceController::class, 'updateDesk'])->whereUuid('desk')->middleware('throttle:30,1')->name('desks.update');

        Route::get('/packages', [TradingFloorResourceController::class, 'packages'])->name('packages.index');
        Route::post('/packages', [TradingFloorResourceController::class, 'storePackage'])->middleware('throttle:20,1')->name('packages.store');
        Route::patch('/packages/{package}', [TradingFloorResourceController::class, 'updatePackage'])->whereUuid('package')->middleware('throttle:30,1')->name('packages.update');

        Route::get('/memberships', [TradingFloorMembershipController::class, 'index'])->name('memberships.index');
        Route::get('/memberships/create', [TradingFloorMembershipController::class, 'create'])->name('memberships.create');
        Route::get('/memberships/seat-map', [TradingFloorMembershipReportController::class, 'seatMap'])->name('memberships.seat_map');
        Route::get('/memberships/reports', [TradingFloorMembershipReportController::class, 'index'])->name('memberships.reports.index');
        Route::get('/memberships/reports/package-revenue.csv', [TradingFloorMembershipReportController::class, 'packageRevenueCsv'])->name('memberships.reports.package_revenue_csv');
        Route::post('/memberships', [TradingFloorMembershipController::class, 'store'])->middleware('throttle:20,1')->name('memberships.store');

        Route::get('/memberships/{membership}', [TradingFloorMembershipController::class, 'show'])->whereUuid('membership')->name('memberships.show');
        Route::get('/memberships/{membership}/invoice', [TradingFloorMembershipDocumentController::class, 'invoice'])->whereUuid('membership')->name('memberships.documents.invoice');
        Route::get('/memberships/{membership}/payments/{payment}/receipt', [TradingFloorMembershipDocumentController::class, 'receipt'])
            ->whereUuid('membership')->whereUuid('payment')->name('memberships.documents.receipt');
        Route::patch('/memberships/{membership}/activate', [TradingFloorMembershipController::class, 'activate'])->whereUuid('membership')->middleware('throttle:20,1')->name('memberships.activate');
        Route::patch('/memberships/{membership}/cancel', [TradingFloorMembershipController::class, 'cancel'])->whereUuid('membership')->middleware('throttle:20,1')->name('memberships.cancel');
        Route::post('/memberships/{membership}/renew', [TradingFloorMembershipController::class, 'renew'])->whereUuid('membership')->middleware('throttle:10,1')->name('memberships.renew');
        Route::patch('/memberships/{membership}/package', [TradingFloorMembershipController::class, 'changePackage'])->whereUuid('membership')->middleware('throttle:10,1')->name('memberships.package.update');
        Route::patch('/memberships/{membership}/transfer', [TradingFloorMembershipController::class, 'transfer'])->whereUuid('membership')->middleware('throttle:15,1')->name('memberships.transfer');
        Route::post('/memberships/{membership}/payments', [TradingFloorMembershipPaymentController::class, 'store'])->whereUuid('membership')->middleware('throttle:20,1')->name('memberships.payments.store');
        Route::patch('/memberships/{membership}/payments/{payment}/reverse', [TradingFloorMembershipPaymentController::class, 'reverse'])
            ->whereUuid('membership')->whereUuid('payment')->middleware('throttle:10,1')->name('memberships.payments.reverse');
    });

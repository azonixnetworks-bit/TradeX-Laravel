<?php

use App\Http\Controllers\Admin\Support\SupportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/support')->name('admin.support.')->middleware('admin.auth')->group(function (): void {
    Route::get('/', [SupportController::class, 'index'])->name('index');
    Route::patch('/availability', [SupportController::class, 'availability'])->name('availability');
    Route::post('/presence/heartbeat', [SupportController::class, 'presenceHeartbeat'])->name('presence.heartbeat');
    Route::get('/settings', [SupportController::class, 'settings'])->name('settings');
    Route::patch('/categories/{supportCategory}', [SupportController::class, 'updateCategory'])->name('categories.update');
    Route::patch('/sla/{supportSlaPolicy}', [SupportController::class, 'updateSla'])->name('sla.update');
    Route::get('/attachments/{supportTicketAttachment}', [SupportController::class, 'attachment'])->name('attachments.download');
    Route::get('/{supportTicket}', [SupportController::class, 'show'])->name('show');
    Route::post('/{supportTicket}/claim', [SupportController::class, 'claim'])->name('claim');
    Route::post('/{supportTicket}/heartbeat', [SupportController::class, 'heartbeat'])->name('heartbeat');
    Route::get('/{supportTicket}/events', [SupportController::class, 'events'])->name('events');
    Route::post('/{supportTicket}/reply', [SupportController::class, 'reply'])->name('reply');
    Route::post('/{supportTicket}/notes', [SupportController::class, 'note'])->name('notes.store');
    Route::patch('/{supportTicket}/status', [SupportController::class, 'transition'])->name('status.update');
});

<?php

use App\Http\Controllers\Api\Support\SupportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/support')->name('api.v1.support.')->middleware(['auth:sanctum', 'tradex.mobile'])->group(function (): void {
    Route::get('/overview', [SupportController::class, 'overview'])->middleware('throttle:api-support-read')->name('overview');
    Route::get('/categories', [SupportController::class, 'categories'])->middleware('throttle:api-support-read')->name('categories');
    Route::get('/faq', [SupportController::class, 'faq'])->middleware('throttle:api-support-read')->name('faq');
    Route::get('/tickets', [SupportController::class, 'tickets'])->middleware('throttle:api-support-read')->name('tickets.index');
    Route::post('/tickets', [SupportController::class, 'store'])->middleware('throttle:api-support-write')->name('tickets.store');
    Route::get('/tickets/{ticket}', [SupportController::class, 'show'])->whereUuid('ticket')->middleware('throttle:api-support-read')->name('tickets.show');
    Route::get('/tickets/{ticket}/messages', [SupportController::class, 'messages'])->whereUuid('ticket')->middleware('throttle:api-support-read')->name('tickets.messages.index');
    Route::post('/tickets/{ticket}/messages', [SupportController::class, 'reply'])->whereUuid('ticket')->middleware('throttle:api-support-message')->name('tickets.messages.store');
    Route::post('/tickets/{ticket}/attachments', [SupportController::class, 'upload'])->whereUuid('ticket')->middleware('throttle:api-support-upload')->name('tickets.attachments.store');
    Route::post('/tickets/{ticket}/reopen', [SupportController::class, 'reopen'])->whereUuid('ticket')->middleware('throttle:api-support-write')->name('tickets.reopen');
    Route::post('/tickets/{ticket}/feedback', [SupportController::class, 'feedback'])->whereUuid('ticket')->middleware('throttle:api-support-write')->name('tickets.feedback');
    Route::get('/attachments/{attachment}/download', [SupportController::class, 'download'])->whereUuid('attachment')->middleware('throttle:api-support-download')->name('attachments.download');
    Route::post('/live-chat/start', [SupportController::class, 'startChat'])->middleware('throttle:api-support-chat-start')->name('chat.start');
    Route::get('/live-chat/{chat}', [SupportController::class, 'chat'])->whereUuid('chat')->middleware('throttle:api-support-read')->name('chat.show');
    Route::post('/live-chat/{chat}/cancel', [SupportController::class, 'cancelChat'])->whereUuid('chat')->middleware('throttle:api-support-write')->name('chat.cancel');
    Route::post('/live-chat/{chat}/end', [SupportController::class, 'endChat'])->whereUuid('chat')->middleware('throttle:api-support-write')->name('chat.end');
    Route::post('/live-chat/{chat}/heartbeat', [SupportController::class, 'heartbeat'])->whereUuid('chat')->middleware('throttle:api-support-heartbeat')->name('chat.heartbeat');
    Route::get('/live-chat/{chat}/events', [SupportController::class, 'events'])->whereUuid('chat')->middleware('throttle:api-support-read')->name('chat.events');
});

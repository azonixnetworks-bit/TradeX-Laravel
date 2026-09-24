<?php

use App\Http\Controllers\Admin\SecondBrainKnowledge\KnowledgeItemController;
use App\Http\Controllers\Admin\SecondBrainKnowledge\KnowledgeSubjectController;
use App\Http\Controllers\Admin\SecondBrainKnowledge\SecondBrainKnowledgeController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::prefix('second-brain-knowledge')
            ->name('second_brain_knowledge.')
            ->group(function (): void {
                Route::get('/', [SecondBrainKnowledgeController::class, 'index'])->name('index');

                Route::get('/subjects', [KnowledgeSubjectController::class, 'index'])->name('subjects');
                Route::post('/subjects', [KnowledgeSubjectController::class, 'store'])->name('subjects.store');

                Route::get('/library', [KnowledgeItemController::class, 'index'])->name('library');
                Route::get('/library/create', [KnowledgeItemController::class, 'create'])->name('library.create');
                Route::post('/library', [KnowledgeItemController::class, 'store'])->name('library.store');
                Route::get('/library/{knowledgeItem}', [KnowledgeItemController::class, 'show'])->name('library.show');

                Route::get('/media/{media}/download', [KnowledgeItemController::class, 'download'])
                    ->name('media.download');

                Route::get('/rules', [SecondBrainKnowledgeController::class, 'rules'])->name('rules');
                Route::get('/examples', [SecondBrainKnowledgeController::class, 'examples'])->name('examples');
                Route::get('/media', [SecondBrainKnowledgeController::class, 'media'])->name('media');
                Route::get('/review', [SecondBrainKnowledgeController::class, 'review'])->name('review');
                Route::get('/rag', [SecondBrainKnowledgeController::class, 'rag'])->name('rag');
                Route::get('/mcp-sync', [SecondBrainKnowledgeController::class, 'mcpSync'])->name('mcp_sync');
                Route::get('/versions', [SecondBrainKnowledgeController::class, 'versions'])->name('versions');
            });
    });

<?php

use App\Http\Controllers\Site\SeoController;
use App\Http\Controllers\Site\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('site.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('site.sitemap');
Route::get('/llms.txt', [SeoController::class, 'llms'])->name('site.llms');

Route::get('/', [SiteController::class, 'home'])->name('site.home');

foreach (config('seo.pages', []) as $key => $page) {
    if ($key === 'home') {
        continue;
    }

    Route::get($page['path'], [SiteController::class, 'page'])
        ->defaults('seo_page', $key)
        ->name('site.'.$key);
}

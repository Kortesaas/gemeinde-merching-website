<?php

use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\ContentController;
use App\Http\Controllers\Public\DocumentDownloadController;
use App\Http\Controllers\Public\MediaController;
use App\Http\Controllers\Public\PlaceholderController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Middleware\ContactFormHeaders;
use Illuminate\Support\Facades\Route;

/*
| Public website routes – middleware group "public" (see bootstrap/app.php).
|
| This group is stateless: no session, no cookies, no CSRF token. Anonymous
| visitors reading the website must not receive any cookie. A future stateful
| public feature (e.g. a contact form) must explicitly opt in with
| ->middleware('web') on exactly the routes that need it.
|
| URLs are defined explicitly (or later resolved through a slug/redirect
| table); they never encode database IDs or the menu hierarchy, so existing
| URLs of the old website can be preserved.
*/

Route::get('/', PlaceholderController::class)->name('public.home');

Route::middleware(['web', ContactFormHeaders::class])->group(function () {
    Route::get('/kontakt', [ContactController::class, 'create'])->block(30, 10)->name('public.contact');
    Route::post('/kontakt', [ContactController::class, 'store'])->middleware('throttle:contact')->block(30, 10)->name('public.contact.store');
    Route::get('/kontakt/bestaetigung', [ContactController::class, 'success'])->name('public.contact.success');
});

Route::get('/medien/{media}', MediaController::class)->whereNumber('media')->name('public.media');

Route::get('/sitemap.xml', SitemapController::class)->name('public.sitemap');
Route::get('/sitemap/{page}.xml', SitemapController::class)->whereNumber('page')->name('public.sitemap.page');

Route::get('/robots.txt', RobotsController::class)->name('public.robots');

// Download URL of documents without their own (legacy) route.
Route::get('/download/{document}/{filename}', DocumentDownloadController::class)
    ->whereNumber('document')->where('filename', '[^/]+')->name('public.document.download');

// Everything else: content routes and redirects from the database
// (App\Services\Routing\RouteManager). Must stay the last public route.
Route::fallback(ContentController::class)->name('public.content');

// Unknown paths are 404 for every method (not 405 because of the GET fallback).
Route::match(['POST', 'PUT', 'PATCH', 'DELETE'], '{any}', fn () => abort(404))->where('any', '.*');

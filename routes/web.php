<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\TagController;
use App\Livewire\HomePage;
use App\Livewire\ShowArticle;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::redirect('/', '/adminpanel', 301);
// Route::get('/', function () {
//     return view('welcome');
// });

// Route::get('/', HomePage::class);

// Route::get('/privacy-policy', [PrivacyController::class, 'privacy'])->name('site.privacy');
// Route::get('/terms-conditions', [PrivacyController::class, 'terms'])->name('site.terms');

// Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
// Route::post('/contact', [ContactController::class, 'send'])->name('contact.send');
// Route::get('/thank-you', function () {
//     return view('contact.thankyou');
// })->name('thankyou');

// Route::get('/tags/{slug}', [TagController::class, 'showArticles'])->name('tags.articles');

// Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
// Route::get('/articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');
// Route::get('/category/{slug}', [ArticleController::class, 'categoryArticles'])->name('category.articles');
// // Route::get('/article/{slug}', ShowArticle::class)->name('articles.show');

// Route::get('/{slug}', [PageController::class, 'show'])->name('pages.page');

<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\PriceAlertController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [GameController::class, 'index'])->name('home');
Route::get('/juego/{game}', [GameController::class, 'show'])->name('game.show');
Route::get('/categoria/{genre}', [GenreController::class, 'show'])->name('genre.show');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::post('/alerts', [PriceAlertController::class, 'store'])->name('alerts.store');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

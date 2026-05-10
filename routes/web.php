<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [GameController::class, 'index'])->name('home');
Route::get('/juego/{game}', [GameController::class, 'show'])->name('game.show');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

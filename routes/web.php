<?php

use App\Http\Controllers\ArticleCommentController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticleReactionController;
use App\Http\Controllers\FixtureController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KzPremierLeagueController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WorldCupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/article/{slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::post('/article/{slug}/comments', [ArticleCommentController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('articles.comments.store');
Route::post('/article/{slug}/reactions', [ArticleReactionController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('articles.reactions.store');
Route::get('/games', [FixtureController::class, 'index'])->name('fixtures.index');
Route::get('/games/feed', [FixtureController::class, 'feed'])->name('fixtures.feed');
Route::get('/games/{externalId}', [FixtureController::class, 'show'])->name('fixtures.show');
Route::get('/clubs', [ClubController::class, 'index'])->name('clubs.index');
Route::get('/countries/{slug}', [CountryController::class, 'show'])->name('countries.show');
Route::get('/clubs/{slug}', [ClubController::class, 'show'])->name('clubs.show');
Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');

Route::prefix('world-cup-2026')->name('world-cup.')->group(function () {
    Route::get('/', [WorldCupController::class, 'show'])->defaults('tab', 'tournament')->name('tournament');
    Route::get('/schedule', [WorldCupController::class, 'show'])->defaults('tab', 'schedule')->name('schedule');
    Route::get('/results', [WorldCupController::class, 'show'])->defaults('tab', 'results')->name('results');
});

Route::prefix('premier-liga')->name('premier-liga.')->group(function () {
    Route::get('/', [KzPremierLeagueController::class, 'show'])->defaults('tab', 'tournament')->name('tournament');
    Route::get('/feed/{tab}', [KzPremierLeagueController::class, 'feed'])->name('feed');
    Route::get('/schedule', [KzPremierLeagueController::class, 'show'])->defaults('tab', 'schedule')->name('schedule');
    Route::get('/results', [KzPremierLeagueController::class, 'show'])->defaults('tab', 'results')->name('results');
    Route::get('/stadiums', [KzPremierLeagueController::class, 'show'])->defaults('tab', 'stadiums')->name('stadiums');
    Route::get('/teams', [KzPremierLeagueController::class, 'show'])->defaults('tab', 'teams')->name('teams');
});

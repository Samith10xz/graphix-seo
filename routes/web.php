<?php

use App\Http\Controllers\AuthController;
use App\Modules\Crawler\Http\ExplorerController;
use App\Modules\Keywords\Http\KeywordController;
use App\Modules\Plan\Http\StepController;
use App\Modules\Projects\Http\ProjectController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/projects')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/setup', [AuthController::class, 'showSetup'])->name('setup');
    Route::post('/setup', [AuthController::class, 'setup'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/crawl-status', [ProjectController::class, 'status'])->name('projects.status');
    Route::post('/projects/{project}/crawl', [ProjectController::class, 'recrawl'])->name('projects.recrawl');

    Route::get('/projects/{project}/explorer', [ExplorerController::class, 'index'])->name('crawler.explorer');
    Route::get('/projects/{project}/issues', [ExplorerController::class, 'issues'])->name('crawler.issues');

    Route::get('/projects/{project}/keywords', [KeywordController::class, 'index'])->name('keywords.index');
    Route::post('/projects/{project}/keywords', [KeywordController::class, 'store'])->name('keywords.store');
    Route::post('/projects/{project}/keywords/analyse', [KeywordController::class, 'analyse'])->name('keywords.analyse');
    Route::patch('/projects/{project}/keywords/{keyword}', [KeywordController::class, 'update'])->name('keywords.update');
    Route::delete('/projects/{project}/keywords/{keyword}', [KeywordController::class, 'destroy'])->name('keywords.destroy');

    // Plan steps (JSON; the step screens arrive with the Competitor Analysis tab)
    Route::patch('/steps/{step}', [StepController::class, 'update']);
    Route::post('/steps/{step}/approve', [StepController::class, 'approve']);
});

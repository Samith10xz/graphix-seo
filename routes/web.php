<?php

use App\Modules\Plan\Http\StepController;
use App\Modules\Projects\Http\ProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['app' => config('app.name'), 'status' => 'ok']));

// Signed-in users (login screens arrive with the Phase 1 UI)
Route::middleware('auth')->group(function () {
    Route::post('/workspaces/{workspace}/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::post('/projects/{project}/crawl', [ProjectController::class, 'recrawl']);

    Route::patch('/steps/{step}', [StepController::class, 'update']);
    Route::post('/steps/{step}/approve', [StepController::class, 'approve']);
});

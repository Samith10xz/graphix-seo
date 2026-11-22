<?php

use App\Modules\Crawler\Http\EngineCallbackController;
use Illuminate\Support\Facades\Route;

// Called only by the Python engine (X-Engine-Key header)
Route::prefix('internal')->middleware('engine.key')->group(function () {
    Route::post('/crawls/{crawl}/pages', [EngineCallbackController::class, 'pages']);
    Route::post('/crawls/{crawl}/finish', [EngineCallbackController::class, 'finish']);
    Route::post('/crawls/{crawl}/fail', [EngineCallbackController::class, 'fail']);
});

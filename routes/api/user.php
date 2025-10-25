<?php

use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\UserActivityController;
use App\Http\Controllers\UserStatsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [ProfileController::class, 'show']);
    Route::get('/stats', [UserStatsController::class, 'index']);
    Route::get('/activities/heatmap', [UserActivityController::class, 'getHeatmap']);
    Route::get('/my-achievements', [UserStatsController::class, 'getUserAchievements']);
    Route::get('/my-rank', [UserStatsController::class, 'getUserRank']);
});

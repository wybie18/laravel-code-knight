<?php

use App\Http\Controllers\BadgeController;
use App\Http\Controllers\LevelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'ability:admin:*'])->group(function () {
    Route::apiResource('gamification/levels', LevelController::class);
    Route::apiResource('gamification/badges', BadgeController::class);
    Route::get('gamification/badge-categories', [BadgeController::class, 'getBadgeCategories']);
});

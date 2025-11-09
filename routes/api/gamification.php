<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\LevelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'ability:admin:*'])->group(function () {
    Route::apiResource('gamification/levels', LevelController::class);
    Route::apiResource('gamification/achievements', AchievementController::class);
    Route::get('gamification/achievement-types', [AchievementController::class, 'getAchievementTypes']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('achievements/my-progress', [AchievementController::class, 'myAchievementsWithProgress']);
    // Additional useful endpoints
    Route::get('achievements/stats', [AchievementController::class, 'myAchievementStats']);
    Route::get('achievements/next-to-unlock', [AchievementController::class, 'nextToUnlock']);
    Route::get('achievements/recently-earned', [AchievementController::class, 'recentlyEarned']);
});

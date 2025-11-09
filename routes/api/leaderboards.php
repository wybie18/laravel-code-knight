<?php

use App\Http\Controllers\LeaderboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    // Generic endpoint - get leaderboard by type
    Route::get('leaderboards', [LeaderboardController::class, 'index']);
    
    // Specific leaderboard endpoints
    Route::get('leaderboards/levels', [LeaderboardController::class, 'levels']);
    Route::get('leaderboards/coding', [LeaderboardController::class, 'coding']);
    Route::get('leaderboards/ctf', [LeaderboardController::class, 'ctf']);
    Route::get('leaderboards/typing', [LeaderboardController::class, 'typing']);
    Route::get('leaderboards/overall', [LeaderboardController::class, 'overall']);
    Route::get('leaderboards/achievements', [LeaderboardController::class, 'achievements']);
    Route::get('leaderboards/streaks', [LeaderboardController::class, 'streaks']);
    Route::get('leaderboards/courses', [LeaderboardController::class, 'courses']);
    
    // Get user's rank in a specific leaderboard
    Route::get('leaderboards/my-rank', [LeaderboardController::class, 'myRank']);
    
    // Get summary of all leaderboards (top 10 in each)
    Route::get('leaderboards/summary', [LeaderboardController::class, 'summary']);
});

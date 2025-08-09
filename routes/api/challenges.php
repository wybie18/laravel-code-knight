<?php

use App\Http\Controllers\Challenge\CodingChallengeController;
use App\Http\Controllers\Challenge\CtfChallengeController;
use App\Http\Controllers\CtfCategoryController;
use App\Http\Controllers\DifficultyController;
use Illuminate\Support\Facades\Route;

Route::get('difficulties/all', [DifficultyController::class, 'getDifficulties']);
Route::get('ctf-categories/all', [CtfCategoryController::class, 'getAllCtfCategories']);

Route::middleware(['auth:sanctum', 'ability:admin:*'])->group(function () {
    Route::apiResource('challenges/ctf', CtfChallengeController::class);
    Route::apiResource('challenges/coding', CodingChallengeController::class);
});

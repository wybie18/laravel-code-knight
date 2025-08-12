<?php

use App\Http\Controllers\Challenge\CodingChallengeController;
use App\Http\Controllers\Challenge\CtfChallengeController;
use App\Http\Controllers\Challenge\TypingChallengeController;
use App\Http\Controllers\CtfCategoryController;
use App\Http\Controllers\DifficultyController;
use App\Http\Controllers\ProgrammingLanguageController;
use Illuminate\Support\Facades\Route;

Route::get('difficulties/all', [DifficultyController::class, 'getDifficulties']);
Route::get('ctf-categories/all', [CtfCategoryController::class, 'getAllCtfCategories']);
Route::get('programming-languages/all', [ProgrammingLanguageController::class, 'getProgrammingLanguages']);

Route::middleware(['auth:sanctum', 'ability:admin:*'])->group(function () {
    Route::apiResource('challenges/ctf', CtfChallengeController::class);
    Route::apiResource('challenges/coding', CodingChallengeController::class);
    Route::apiResource('challenges/typing', TypingChallengeController::class);
});

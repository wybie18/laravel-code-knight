<?php

use App\Http\Controllers\Challenge\ChallengeCodeExecutionController;
use App\Http\Controllers\Challenge\ChallengeController;
use App\Http\Controllers\Challenge\CodingChallengeController;
use App\Http\Controllers\Challenge\CtfChallengeController;
use App\Http\Controllers\Challenge\CtfSubmissionController;
use App\Http\Controllers\Challenge\TypingChallengeController;
use App\Http\Controllers\Challenge\TypingSubmissionController;
use App\Http\Controllers\CtfCategoryController;
use App\Http\Controllers\DifficultyController;
use App\Http\Controllers\ProgrammingLanguageController;
use Illuminate\Support\Facades\Route;

Route::get('difficulties/all', [DifficultyController::class, 'getDifficulties']);
Route::get('ctf-categories/all', [CtfCategoryController::class, 'getAllCtfCategories']);
Route::get('programming-languages/all', [ProgrammingLanguageController::class, 'getProgrammingLanguages']);

Route::middleware(['auth:sanctum', 'ability:admin:*'])->group(function () {
    Route::apiResource('challenges/ctf', CtfChallengeController::class)->except(['index', 'show']);
    Route::apiResource('challenges/coding', CodingChallengeController::class)->except(['index', 'show']);
    Route::apiResource('challenges/typing', TypingChallengeController::class)->except(['index', 'show']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('challenges/ctf', [CtfChallengeController::class, 'index']);
    Route::get('challenges/ctf/{ctf}', [CtfChallengeController::class, 'show']);
    Route::post('challenges/ctf/{ctf}/submit', [CtfSubmissionController::class, 'store']);

    Route::get('challenges/coding', [CodingChallengeController::class, 'index']);
    Route::get('challenges/coding/{coding}', [CodingChallengeController::class, 'show']);
    Route::post('challenges/coding/{coding}/execute-code', [ChallengeCodeExecutionController::class, 'executeCode']);
    Route::post('challenges/coding/{coding}/submit', [ChallengeCodeExecutionController::class, 'submitCode']);
    Route::get('challenges/coding/{coding}/submissions', [ChallengeCodeExecutionController::class, 'getSubmissionHistory']);

    Route::get('challenges/typing', [TypingChallengeController::class, 'index']);
    Route::get('challenges/typing/{typing}', [TypingChallengeController::class, 'show']);
    Route::post('challenges/typing/{typing}/submit', [TypingSubmissionController::class, 'store']);
    Route::get('challenges/typing/{typing}/submissions', [TypingSubmissionController::class, 'getSubmissionHistory']);

    Route::get('challenges/solved', [ChallengeController::class, 'mySolvedChallenges']);
    Route::get('challenges/progress', [ChallengeController::class, 'getChallengesProgress']);
});

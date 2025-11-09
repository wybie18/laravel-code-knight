<?php

use App\Http\Controllers\AchievementTypeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CourseCategoryController;
use App\Http\Controllers\CtfCategoryController;
use App\Http\Controllers\DifficultyController;
use App\Http\Controllers\ProgrammingLanguageController;
use App\Http\Controllers\SkillTagController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('settings/categories/courses', CourseCategoryController::class)
        ->only(['index', 'show']);

    Route::apiResource('settings/categories/courses', CourseCategoryController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('ability:admin:*');

    Route::apiResource('settings/categories/ctf', CtfCategoryController::class)
        ->only(['index', 'show']);

    Route::apiResource('settings/categories/ctf', CtfCategoryController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('ability:admin:*');

    Route::apiResource('settings/types/achievements', AchievementTypeController::class)
        ->only(['index', 'show']);

    Route::apiResource('settings/types/achievements', AchievementTypeController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('ability:admin:*');

    Route::apiResource('settings/difficulties', DifficultyController::class)
        ->only(['index', 'show']);

    Route::apiResource('settings/difficulties', DifficultyController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('ability:admin:*');

    Route::apiResource('settings/programming-languages', ProgrammingLanguageController::class)
        ->only(['index', 'show']);

    Route::apiResource('settings/programming-languages', ProgrammingLanguageController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('ability:admin:*');

    Route::apiResource('settings/skill-tags', SkillTagController::class)
        ->only(['index', 'show']);

    Route::apiResource('settings/skill-tags', SkillTagController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('ability:admin:*');
});
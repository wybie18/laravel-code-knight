<?php

use App\Http\Controllers\AchievementTypeController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BadgeCategoryController;
use App\Http\Controllers\CourseCategoryController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\Course\LessonController;
use App\Http\Controllers\CtfCategoryController;
use App\Http\Controllers\DifficultyController;
use App\Http\Controllers\ProgrammingLanguageController;
use App\Http\Controllers\SkillTagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::apiResource('courses', CourseController::class);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('courses', CourseController::class);

    Route::apiResource('courses.lessons', LessonController::class)
        ->only(['index', 'store'])
        ->scoped();

    Route::apiResource('lessons', LessonController::class)
        ->only(['show', 'update', 'destroy']);

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

    Route::apiResource('settings/categories/badges', BadgeCategoryController::class)
        ->only(['index', 'show']);

    Route::apiResource('settings/categories/badges', BadgeCategoryController::class)
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

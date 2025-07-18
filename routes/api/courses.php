<?php

use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\Course\LessonController;
use Illuminate\Support\Facades\Route;

Route::apiResource('courses', CourseController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('courses', CourseController::class);

    Route::apiResource('courses.lessons', LessonController::class)
        ->only(['index', 'store'])
        ->scoped();

    Route::apiResource('lessons', LessonController::class)
        ->only(['show', 'update', 'destroy']);
});

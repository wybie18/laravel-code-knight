<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\Course\LessonController;
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
});

<?php

use App\Http\Controllers\Course\ActivityController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\Course\CourseModuleController;
use App\Http\Controllers\Course\LessonController;
use App\Http\Controllers\CourseCategoryController;
use App\Http\Controllers\SkillTagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('courses', CourseController::class)->except(['index', 'show']);

    Route::apiResource('courses.modules', CourseModuleController::class)
        ->except(['index', 'show'])
        ->parameters(['courses' => 'course', 'modules' => 'module']);

    Route::get('courses/{course}/modules', [CourseModuleController::class, 'index'])
        ->name('courses.modules.index');
    Route::get('courses/{course}/modules/{module}', [CourseModuleController::class, 'show'])
        ->name('courses.modules.show');

    Route::apiResource('courses.modules.lessons', LessonController::class)
        ->except(['index', 'show'])
        ->parameters([
            'courses' => 'course',
            'modules' => 'module',
            'lessons' => 'lesson',
        ]);

    Route::get('courses/{course}/modules/{module}/lessons', [LessonController::class, 'index'])
        ->name('courses.modules.lessons.index');
    Route::get('courses/{course}/modules/{module}/lessons/{lesson}', [LessonController::class, 'show'])
        ->name('courses.modules.lessons.show');
    
    Route::apiResource('courses.modules.lessons.activities', ActivityController::class);
});
Route::get('course-categories/all', [CourseCategoryController::class, 'getAllCourseCategories']);
Route::get('skill-tags/all', [SkillTagController::class, 'getAllSkillTags']);
Route::get('courses', [CourseController::class, 'index']);
Route::get('courses/{course}', [CourseController::class, 'show']);
Route::get('courses/{course}/modules', [CourseModuleController::class, 'index']);
Route::get('courses/{course}/modules/{module}', [CourseModuleController::class, 'show']);

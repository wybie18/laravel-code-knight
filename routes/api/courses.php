<?php

use App\Http\Controllers\Course\ActivityController;
use App\Http\Controllers\Course\CourseCodeExecutionController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\Course\CourseModuleController;
use App\Http\Controllers\Course\CourseWithContentController;
use App\Http\Controllers\Course\LessonController;
use App\Http\Controllers\CourseCategoryController;
use App\Http\Controllers\SkillTagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/student/courses/my-progress', [CourseController::class, 'myCoursesWithProgress']);
    Route::apiResource('courses', CourseController::class)->except(['index']);
    Route::get('courses/{course}/completion', [CourseController::class, 'completion']);
    Route::post('courses/store/content', [CourseWithContentController::class, 'storeWithContent']);
    Route::put('/courses/{course}/content', [CourseWithContentController::class, 'updateWithContent']);

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
    
    Route::post('/lessons/{lesson}/complete', [LessonController::class, 'markCompleted']);
    
    Route::apiResource('courses.modules.activities', ActivityController::class);

    Route::post('/playground/execute-code', [CourseCodeExecutionController::class, 'executeCode']);
    Route::post('/activities/{activity}/submit-code', [ActivityController::class, 'submitCode']);
    Route::post('/activities/{activity}/submit-quiz', [ActivityController::class, 'submitQuiz']);
});
Route::get('course-categories/all', [CourseCategoryController::class, 'getAllCourseCategories']);
Route::get('skill-tags/all', [SkillTagController::class, 'getAllSkillTags']);
Route::get('courses', [CourseController::class, 'index']);
Route::get('courses/{course}/modules', [CourseModuleController::class, 'index']);
Route::get('courses/{course}/modules/{module}', [CourseModuleController::class, 'show']);

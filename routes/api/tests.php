<?php

use App\Http\Controllers\Test\TestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Test API Routes
|--------------------------------------------------------------------------
|
| Routes for test/exam management
| Teachers can create and manage tests
| Students can view assigned tests and submit attempts
|
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // Student Routes - Tests assigned to them
    Route::prefix('my-tests')->group(function () {
        Route::get('/', [TestController::class, 'myTests']);
        Route::get('/{test:slug}', [TestController::class, 'show']);
        Route::get('/{test:slug}/attempts', [TestController::class, 'myAttempts']);
        Route::post('/{test:slug}/start', [TestController::class, 'startAttempt']);
        Route::get('/{test:slug}/attempts/{attempt}', [TestController::class, 'getAttempt']);
        Route::post('/{test:slug}/attempts/{attempt}/items/{testItem}/submit', [TestController::class, 'submitItemAnswer']);
        Route::post('/{test:slug}/attempts/{attempt}/submit', [TestController::class, 'submitTest']);
        Route::get('/{test:slug}/attempts/{attempt}/items/{testItem}/execute', [TestController::class, 'executeCode']);
    });

    // Teacher & Admin Routes - Test Management
    Route::middleware(['ability:tests:view,admin:*'])->prefix('tests')->group(function () {

        // List and view tests
        Route::get('/', [TestController::class, 'index']);
        Route::get('/{test:slug}', [TestController::class, 'show']);

        // Create tests
        Route::middleware(['ability:tests:create,admin:*'])->group(function () {
            Route::post('/', [TestController::class, 'store']);
        });

        // Update tests
        Route::middleware(['ability:tests:update,admin:*'])->group(function () {
            Route::put('/{test:slug}', [TestController::class, 'update']);
            Route::patch('/{test:slug}', [TestController::class, 'update']);
        });

        // Delete tests
        Route::middleware(['ability:tests:delete,admin:*'])->group(function () {
            Route::delete('/{test:slug}', [TestController::class, 'destroy']);
        });

        // Test Items Management
        Route::middleware(['ability:tests:manage,admin:*'])->prefix('{test:slug}/items')->group(function () {
            Route::post('/', [TestController::class, 'addItems']);
            Route::post('/quiz-question', [TestController::class, 'createQuizQuestion']);
            Route::post('/essay-question', [TestController::class, 'createEssayQuestion']);
            Route::post('/coding-challenge', [TestController::class, 'createCodingChallenge']);
            Route::post('/add-challenge', [TestController::class, 'addExistingChallenge']);
            Route::put('/{testItem}', [TestController::class, 'updateItem']);
            Route::patch('/{testItem}', [TestController::class, 'updateItem']);
            Route::delete('/{testItem}', [TestController::class, 'removeItem']);
        });

        // Student Assignment Management
        Route::middleware(['ability:tests:manage,admin:*'])->prefix('{test:slug}/students')->group(function () {
            Route::get('/', [TestController::class, 'getStudents']);
            Route::post('/assign', [TestController::class, 'assignStudents']);
            Route::post('/remove', [TestController::class, 'removeStudents']);
        });

        // Grading and Attempt Management
        Route::middleware(['ability:tests:manage,admin:*'])->prefix('{test:slug}')->group(function () {
            Route::get('/results', [TestController::class, 'getResults']);
            Route::get('/attempts/{attempt}', [TestController::class, 'getAttempt']);
            Route::post('/attempts/{attempt}/submissions/{submission}/grade', [TestController::class, 'gradeSubmission']);
            Route::get('/pending-submissions', [TestController::class, 'getPendingSubmissions']);
            Route::post('/close', [TestController::class, 'closeTest']);
        });
    });

    // Course-specific tests
    Route::prefix('courses/{course:slug}/tests')->group(function () {
        Route::get('/', [TestController::class, 'getTestsByCourse']);
    });
});

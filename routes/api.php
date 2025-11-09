<?php

use App\Http\Controllers\FileUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/upload', [FileUploadController::class, 'upload']);

require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/challenges.php';
require __DIR__ . '/api/courses.php';
require __DIR__ . '/api/settings.php';
require __DIR__ . '/api/gamification.php';
require __DIR__ . '/api/users.php';
require __DIR__ . '/api/user.php';
require __DIR__ . '/api/leaderboards.php';
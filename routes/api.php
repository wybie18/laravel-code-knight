<?php

use App\Http\Controllers\FileUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/upload', [FileUploadController::class, 'upload']);

Route::get('/download/apk', function () {
    $filePath = storage_path('app/apk/app-release.apk');

    if (!file_exists($filePath)) {
        abort(404, 'File not found.');
    }

    return response()->download($filePath, 'codeknight.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
});

require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/challenges.php';
require __DIR__ . '/api/courses.php';
require __DIR__ . '/api/settings.php';
require __DIR__ . '/api/gamification.php';
require __DIR__ . '/api/users.php';
require __DIR__ . '/api/user.php';
require __DIR__ . '/api/leaderboards.php';
require __DIR__ . '/api/tests.php';
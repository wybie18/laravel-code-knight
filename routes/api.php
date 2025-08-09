<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/challenges.php';
require __DIR__ . '/api/courses.php';
require __DIR__ . '/api/settings.php';
require __DIR__ . '/api/gamification.php';
require __DIR__ . '/api/users.php';
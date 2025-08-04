<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('roles/all', [UserRoleController::class, 'getUserRoles']);
});

Route::middleware(['auth:sanctum', 'ability:admin:*'])->group(function () {
    Route::apiResource('roles', UserRoleController::class);
    Route::apiResource('users', UserController::class);
});

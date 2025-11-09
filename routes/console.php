<?php

use App\Services\VerificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule to clean up expired verification codes daily
Schedule::call(function () {
    $verificationService = app(VerificationService::class);
    $deleted = $verificationService->cleanupExpiredCodes();
    logger()->info("Cleaned up {$deleted} expired verification codes");
})->daily();

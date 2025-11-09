<?php

namespace App\Services;

use App\Mail\EmailVerificationMail;
use App\Mail\PasswordResetMail;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Mail;

class VerificationService
{
    /**
     * Generate a 6-digit verification code
     */
    public function generateCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create and send email verification code
     */
    public function sendEmailVerificationCode(string $email, ?string $userName = null): VerificationCode
    {
        // Delete any existing unused verification codes for this email
        VerificationCode::where('email', $email)
            ->where('type', 'email_verification')
            ->where('is_used', false)
            ->delete();

        // Generate new code
        $code = $this->generateCode();

        // Create verification code record
        $verificationCode = VerificationCode::create([
            'email' => $email,
            'code' => $code,
            'type' => 'email_verification',
            'expires_at' => now()->addMinutes(10), // 10 minutes expiry
            'is_used' => false,
        ]);

        // Send email
        Mail::to($email)->send(new EmailVerificationMail($code, $userName));

        return $verificationCode;
    }

    /**
     * Create and send password reset code
     */
    public function sendPasswordResetCode(string $email, ?string $userName = null): VerificationCode
    {
        // Delete any existing unused password reset codes for this email
        VerificationCode::where('email', $email)
            ->where('type', 'password_reset')
            ->where('is_used', false)
            ->delete();

        // Generate new code
        $code = $this->generateCode();

        // Create verification code record
        $verificationCode = VerificationCode::create([
            'email' => $email,
            'code' => $code,
            'type' => 'password_reset',
            'expires_at' => now()->addMinutes(15), // 15 minutes expiry
            'is_used' => false,
        ]);

        // Send email
        Mail::to($email)->send(new PasswordResetMail($code, $userName));

        return $verificationCode;
    }

    /**
     * Verify a code
     */
    public function verifyCode(string $email, string $code, string $type): bool
    {
        $verificationCode = VerificationCode::where('email', $email)
            ->where('code', $code)
            ->where('type', $type)
            ->valid()
            ->first();

        if (!$verificationCode) {
            return false;
        }

        // Mark as used
        $verificationCode->markAsUsed();

        return true;
    }

    /**
     * Check if a code is valid without marking it as used
     */
    public function isCodeValid(string $email, string $code, string $type): bool
    {
        return VerificationCode::where('email', $email)
            ->where('code', $code)
            ->where('type', $type)
            ->valid()
            ->exists();
    }

    /**
     * Get the verification code record
     */
    public function getVerificationCode(string $email, string $code, string $type): ?VerificationCode
    {
        return VerificationCode::where('email', $email)
            ->where('code', $code)
            ->where('type', $type)
            ->valid()
            ->first();
    }

    /**
     * Clean up expired codes (can be run via scheduled task)
     */
    public function cleanupExpiredCodes(): int
    {
        return VerificationCode::where('expires_at', '<', now())->delete();
    }
}

<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LevelService;
use App\Services\VerificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }
    /**
     * Register a new user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function register(Request $request)
    {
        $request->validate([
            'username'    => ['required', 'string', 'max:255', 'unique:users'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'    => ['required', 'string', 'min:8', 'confirmed'],
            'first_name'  => ['required', 'string', 'max:255'],
            'last_name'   => ['required', 'string', 'max:255'],
            'student_id'  => ['nullable', 'string', 'max:255', 'unique:users'],
            'avatar'      => ['nullable', 'string', 'max:255'],
            'role_id'     => ['nullable', 'exists:user_roles,id'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::create([
            'username'      => $request->username,
            'email'         => $request->email,
            'password'      => Hash::make($request->password),
            'first_name'    => $request->first_name,
            'last_name'     => $request->last_name,
            'student_id'    => $request->student_id,
            'avatar'        => $request->avatar,
            'role_id'       => $request->role_id ?? 2, // Default to a 'student' role if not provided which is ID 2
            'total_xp'      => 0,                      // Initialize XP
            'current_level' => 1,                      // Initialize level
        ]);

        // Dispatch the Registered event if you have listeners for it (e.g., sending email verification)
        event(new Registered($user));

        // Send email verification code
        $this->verificationService->sendEmailVerificationCode(
            $user->email,
            $user->first_name . ' ' . $user->last_name
        );

        // If the user has an existing token with the same device name, delete it
        $user->tokens()->where('name', $request->device_name)->delete();

        // Create a new Sanctum personal access token for the user with 7 days expiration
        $expiresAt     = now()->addDays(7);
        $tokenInstance = $user->createToken($request->device_name, ['*'], $expiresAt);
        $token         = $tokenInstance->plainTextToken;

        // Update the token's expires_at in the database
        $tokenInstance->accessToken->expires_at = $expiresAt;
        $tokenInstance->accessToken->save();

        $stats = null;
        if ($user->role->name === 'student') {
            $stats = $this->getUserStats($user);
        }

        return response()->json([
            'success' => true,
            'message' => 'Account registered successfully! Please check your email for verification code.',
            'data'    => [
                'token' => $token,
                'user'  => [
                    'id'             => $user->id,
                    'username'       => $user->username,
                    'first_name'     => $user->first_name,
                    'last_name'      => $user->last_name,
                    'avatar'         => $this->getAvatarUrl($user->avatar),
                    'student_id'     => $user->student_id,
                    'email'          => $user->email,
                    'email_verified' => false,
                    'role'           => $user->role->name ?? null,
                ],
                'stats' => $stats,
            ],
        ], 201);
    }

    /**
     * Verify user email with code
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code'  => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.'],
            ]);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified.',
            ], 400);
        }

        $isValid = $this->verificationService->verifyCode(
            $request->email,
            $request->code,
            'email_verification'
        );

        if (! $isValid) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired verification code.'],
            ]);
        }

        // Mark email as verified
        $user->email_verified_at = now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully!',
        ], 200);
    }

    /**
     * Resend email verification code
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified.',
            ], 400);
        }

        $this->verificationService->sendEmailVerificationCode(
            $user->email,
            $user->first_name . ' ' . $user->last_name
        );

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent successfully!',
        ], 200);
    }

    /**
     * Send password reset code
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $request->email)->first();

        $this->verificationService->sendPasswordResetCode(
            $user->email,
            $user->first_name . ' ' . $user->last_name
        );

        return response()->json([
            'success' => true,
            'message' => 'Password reset code sent to your email.',
        ], 200);
    }

    /**
     * Verify password reset code
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code'  => ['required', 'string', 'size:6'],
        ]);

        $isValid = $this->verificationService->isCodeValid(
            $request->email,
            $request->code,
            'password_reset'
        );

        if (! $isValid) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired verification code.'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code is valid.',
        ], 200);
    }

    /**
     * Reset password with verification code
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email', 'exists:users,email'],
            'code'     => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $request->email)->first();

        $isValid = $this->verificationService->verifyCode(
            $request->email,
            $request->code,
            'password_reset'
        );

        if (! $isValid) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired verification code.'],
            ]);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please login with your new password.',
        ], 200);
    }

    /**
     * Authenticate user and issue an API token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required'],
            'device_name' => ['required', 'string', 'max:255'],
            'remember'    => ['nullable', 'boolean'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->tokens()->where('name', $request->device_name)->delete();

        $token = '';

        $abilities = [];
        if ($user->role->name == "admin") {
            $abilities = ['admin:*'];
        } else if ($user->role->name == "teacher") {
            $abilities = [
                'courses:create',
                'courses:update',
                'courses:delete',
                'courses:view',
                'challenges:create',
                'challenges:update',
                'challenges:delete',
                'challenge:view',
                'tests:create',
                'tests:update',
                'tests:delete',
                'tests:view',
                'tests:manage',
                'students:view',
                'submissions:view',
            ];
        } else {
            $abilities = [
                'profile:view',
                'profile:update',
                'courses:view',
                'challenge:view',
                'activity:view',
                'submissions:create',
                'submissions:view-own',
                'submissions:update-own',
            ];
        }

        // Set token expiration: 30 days if remember is true, 7 days otherwise
        $remember  = $request->input('remember', false);
        $expiresAt = $remember ? now()->addDays(30) : now()->addDays(7);

        $tokenInstance = $user->createToken($request->device_name, $abilities, $expiresAt);
        $token         = $tokenInstance->plainTextToken;

        // Update the token's expires_at in the database
        $tokenInstance->accessToken->expires_at = $expiresAt;
        $tokenInstance->accessToken->save();

        $stats = null;
        if ($user->role->name === 'student') {
            $stats = $this->getUserStats($user);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful!',
            'data'    => [
                'token' => $token,
                'user'  => [
                    'id'         => $user->id,
                    'username'   => $user->username,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'avatar'     => $this->getAvatarUrl($user->avatar),
                    'student_id' => $user->student_id,
                    'email'      => $user->email,
                    'role'       => $user->role->name ?? null,
                ],
                'stats' => $stats,
            ],
        ], 200);
    }

    /**
     * Invalidate the current user's API token (logout).
     * This endpoint requires authentication.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.'], 200);
    }

    private function getUserStats(User $user)
    {
        $levelService = app(LevelService::class);
        $levelInfo    = $levelService->getUserLevelInfo($user);
        return [
            'level'                 => $levelInfo,
            'courses_enrolled'      => $user->courseEnrollments()->count(),
            'courses_completed'     => $user->courseProgress()->whereNotNull('completed_at')->count(),
            'activities_completed'  => $user->activityProgress()->whereNotNull('completed_at')->count(),
            'achievements_earned'   => $user->achievements()->count(),
            'current_streak'        => $user->streaks()->latest()->first()->current_streak ?? 0,
            'longest_streak'        => $user->streaks()->latest()->first()->longest_streak ?? 0,
            'total_submissions'     => $user->activitySubmissions()->count(),
            'challenge_submissions' => $user->challengeSubmissions()->count(),
            'challenges_completed'  => $user->challengeSubmissions()->where('is_correct', true)->distinct('challenge_id')->count(),
        ];
    }

    /**
     * Get the avatar URL, handling both local paths and external URLs.
     *
     * @param string|null $avatar
     * @return string|null
     */
    private function getAvatarUrl($avatar)
    {
        if (! $avatar) {
            return null;
        }

        // Check if avatar is already a full URL (starts with http:// or https://)
        if (filter_var($avatar, FILTER_VALIDATE_URL)) {
            return $avatar;
        }

        // Otherwise, treat it as a local storage path
        return url(Storage::url($avatar));
    }
}

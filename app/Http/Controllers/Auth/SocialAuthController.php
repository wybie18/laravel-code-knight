<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LevelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect the user to the OAuth Provider.
     *
     * @param string $provider
     * @return \Illuminate\Http\JsonResponse
     */
    public function redirectToProvider($provider)
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Obtain the user information from OAuth Provider.
     *
     * @param string $provider
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleProviderCallback($provider, Request $request)
    {
        try {
            $this->validateProvider($provider);

            // Get device_name from query parameters (passed by frontend)
            $deviceName = $request->query('device_name', 'Unknown Device');

            if (! $deviceName) {
                return redirect()->away(config('app.frontend_url') . "/auth/error=" . urlencode('The device name field is required.'));
            }

            // Get the OAuth user using the code from query parameters
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Find or create user
            $user = $this->findOrCreateUser($socialUser, $provider);

            // Delete existing tokens with the same device name
            $user->tokens()->where('name', $deviceName)->delete();

            // Create a new Sanctum personal access token with 7 days expiration
            $expiresAt     = now()->addDays(7);
            $tokenInstance = $user->createToken($deviceName, ['*'], $expiresAt);
            $token         = $tokenInstance->plainTextToken;

            // Update the token's expires_at in the database
            $tokenInstance->accessToken->expires_at = $expiresAt;
            $tokenInstance->accessToken->save();

            $stats = null;
            if ($user->role->name === 'student') {
                $stats = $this->getUserStats($user);
            }

            // Build query parameters
            $queryParams = [
                'token' => $token,
                'provider' => $provider,
                'user_id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'student_id' => $user->student_id,
                'role' => $user->role->name,
                'avatar' => $this->getAvatarUrl($user->avatar),
                'stats' => json_encode($stats),
            ];

            return redirect()->away(config('app.frontend_url') . "/auth/callback?" . http_build_query($queryParams));
        } catch (\Exception $e) {
            return redirect()->away(config('app.frontend_url') . "/auth/error?" . http_build_query([
                'message' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Find or create a user based on the provider data.
     *
     * @param \Laravel\Socialite\Contracts\User $socialUser
     * @param string $provider
     * @return User
     */
    protected function findOrCreateUser($socialUser, $provider)
    {
        // Try to find user by provider and provider_id
        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($user) {
            return $user;
        }

        // Try to find user by email
        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            // Link the provider to existing user
            $user->update([
                'provider'    => $provider,
                'provider_id' => $socialUser->getId(),
            ]);
            return $user;
        }

        // Create new user
        $nameParts = $this->parseUserName($socialUser->getName());

        return User::create([
            'username'          => $this->generateUniqueUsername($socialUser->getNickname() ?? $nameParts['first_name']),
            'email'             => $socialUser->getEmail(),
            'first_name'        => $nameParts['first_name'],
            'last_name'         => $nameParts['last_name'],
            'avatar'            => $socialUser->getAvatar(), // Store external OAuth avatar URL
            'provider'          => $provider,
            'provider_id'       => $socialUser->getId(),
            'password'          => Hash::make(Str::random(32)), // Random password for OAuth users
            'role_id'           => 2,                           // Default to 'student' role
            'total_xp'          => 0,
            'current_level'     => 1,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Parse full name into first and last name.
     *
     * @param string $fullName
     * @return array
     */
    protected function parseUserName($fullName)
    {
        $nameParts = explode(' ', $fullName, 2);

        return [
            'first_name' => $nameParts[0] ?? 'User',
            'last_name'  => $nameParts[1] ?? '',
        ];
    }

    /**
     * Generate a unique username.
     *
     * @param string $baseUsername
     * @return string
     */
    protected function generateUniqueUsername($baseUsername)
    {
        // Clean the base username
        $username = Str::slug($baseUsername, '');
        $username = preg_replace('/[^a-zA-Z0-9]/', '', $username);

        // If empty after cleaning, use a default
        if (empty($username)) {
            $username = 'user';
        }

        $originalUsername = $username;
        $counter          = 1;

        // Keep checking until we find a unique username
        while (User::where('username', $username)->exists()) {
            $username = $originalUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Validate the provider.
     *
     * @param string $provider
     * @return void
     * @throws \Exception
     */
    protected function validateProvider($provider)
    {
        $allowedProviders = ['github', 'google'];

        if (! in_array($provider, $allowedProviders)) {
            throw new \Exception('Invalid provider. Allowed providers: ' . implode(', ', $allowedProviders));
        }
    }

    /**
     * Get user statistics (copied from AuthController for consistency).
     *
     * @param User $user
     * @return array|null
     */
    protected function getUserStats($user)
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
     * @return string
     */
    protected function getAvatarUrl($avatar): string
    {
        if (!$avatar) {
            return '';
        }

        // Check if avatar is already a full URL (starts with http:// or https://)
        if (filter_var($avatar, FILTER_VALIDATE_URL)) {
            return $avatar;
        }

        // Otherwise, treat it as a local storage path
        return url(Storage::url($avatar));
    }
}

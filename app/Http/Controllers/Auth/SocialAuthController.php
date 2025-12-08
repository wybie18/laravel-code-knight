<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LevelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function prepareOAuth(Request $request, $provider)
    {
        $this->validateProvider($provider);

        $request->validate([
            'role'         => 'nullable|string|in:teacher,student',
            'device_name'  => 'required|string|max:255',
            'redirect_url' => 'nullable|string',
        ]);

        $stateToken = Str::random(40);
        Cache::put("oauth_state_{$stateToken}", [
            'role'         => $request->role,
            'device_name'  => $request->device_name,
            'redirect_url' => $request->redirect_url,
        ], now()->addMinutes(10));

        $redirectUrl = Socialite::driver($provider)
            ->stateless()
            ->with(['state' => $stateToken])
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'success' => true,
            'url'     => $redirectUrl,
        ]);
    }

    /**
     * Obtain the user information from OAuth Provider.
     *
     * @param string $provider
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleProviderCallback($provider, Request $request)
    {
        $clientRedirectUrl = null;

        try {
            $this->validateProvider($provider);

            $stateToken = $request->query('state');

            if (! $stateToken) {
                return redirect()->away(config('app.frontend_url') . "/auth/error?" . http_build_query([
                    'message' => 'Invalid OAuth state. Please try again.',
                ]));
            }

            $oauthData = Cache::pull("oauth_state_{$stateToken}");

            if (! $oauthData) {
                return redirect()->away(config('app.frontend_url') . "/auth/error?" . http_build_query([
                    'message' => 'OAuth session expired. Please try again.',
                ]));
            }

            $role       = $oauthData['role'];
            $deviceName = $oauthData['device_name'];
            $clientRedirectUrl = $oauthData['redirect_url'] ?? null;

            Log::info("OAuth login attempt for role: {$role}, device: {$deviceName}");

            $socialUser = Socialite::driver($provider)->stateless()->user();

            $user = $this->findOrCreateUser($socialUser, $provider, $role);

            $user->tokens()->where('name', $deviceName)->delete();

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
            $expiresAt     = now()->addDays(7);
            $tokenInstance = $user->createToken($deviceName, $abilities, $expiresAt);
            $token         = $tokenInstance->plainTextToken;

            $tokenInstance->accessToken->expires_at = $expiresAt;
            $tokenInstance->accessToken->save();

            $stats = null;
            if ($user->role->name === 'student') {
                $stats = $this->getUserStats($user);
            }

            $queryParams = [
                'token'      => $token,
                'provider'   => $provider,
                'user_id'    => $user->id,
                'username'   => $user->username,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
                'student_id' => $user->student_id,
                'role'       => $user->role->name,
                'avatar'     => $this->getAvatarUrl($user->avatar),
                'stats'      => json_encode($stats),
            ];

            $baseUrl = $clientRedirectUrl ?? (config('app.frontend_url') . "/auth/callback");
            $separator = parse_url($baseUrl, PHP_URL_QUERY) ? '&' : '?';
            return redirect()->away($baseUrl . $separator . http_build_query($queryParams));
        } catch (\Exception $e) {
            if ($clientRedirectUrl) {
                $separator = parse_url($clientRedirectUrl, PHP_URL_QUERY) ? '&' : '?';
                return redirect()->away($clientRedirectUrl . $separator . http_build_query([
                    'error'   => 'true',
                    'message' => $e->getMessage(),
                ]));
            }

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
     * @param string $role
     * @return User
     */
    protected function findOrCreateUser($socialUser, $provider, $role)
    {
        // Try to find user by provider and provider_id
        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($user) {
            return $user;
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            $user->update([
                'provider'    => $provider,
                'provider_id' => $socialUser->getId(),
            ]);
            return $user;
        }

        $roleId = DB::table('user_roles')->where('name', $role)->value('id');

        if (! $roleId) {
            $roleId = DB::table('user_roles')->where('name', 'student')->value('id');
        }

        $nameParts = $this->parseUserName($socialUser->getName());

        return User::create([
            'username'          => $this->generateUniqueUsername($socialUser->getNickname() ?? $nameParts['first_name']),
            'email'             => $socialUser->getEmail(),
            'first_name'        => $nameParts['first_name'],
            'last_name'         => $nameParts['last_name'],
            'avatar'            => $socialUser->getAvatar(),
            'provider'          => $provider,
            'provider_id'       => $socialUser->getId(),
            'password'          => Hash::make(Str::random(32)),
            'role_id'           => $roleId,
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
        $username = Str::slug($baseUsername, '');
        $username = preg_replace('/[^a-zA-Z0-9]/', '', $username);

        if (empty($username)) {
            $username = 'user';
        }

        $originalUsername = $username;
        $counter          = 1;

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
        if (! $avatar) {
            return '';
        }

        // Check if avatar is already a full URL (starts with http:// or https://)
        if (filter_var($avatar, FILTER_VALIDATE_URL)) {
            return $avatar;
        }

        // Otherwise, treadt it as a local storage path
        return url(Storage::url($avatar));
    }
}

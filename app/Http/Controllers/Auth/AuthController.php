<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Registered;

class AuthController extends Controller
{
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
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'student_id' => ['nullable', 'string', 'max:255', 'unique:users'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'role_id' => ['nullable', 'exists:user_roles,id'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);
        
        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'student_id' => $request->student_id,
            'avatar' => $request->avatar,
            'role_id' => $request->role_id ?? 2, // Default to a 'student' role if not provided which is ID 2
            'total_xp' => 0, // Initialize XP
            'current_level' => 1, // Initialize level
        ]);

        // Dispatch the Registered event if you have listeners for it (e.g., sending email verification)
        event(new Registered($user));

        // If the user has an existing token with the same device name, delete it
        $user->tokens()->where('name', $request->device_name)->delete();
        // Create a new Sanctum personal access token for the user
        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Account registered successfully!',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'student_id' => $user->student_id,
                    'email' => $user->email,
                    'role' => $user->role->name ?? null,
                ]
            ]
        ], 201);
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
            'email' => ['required', 'email'],
            'password' => ['required'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // If the user has an existing token with the same device name, delete it
        $user->tokens()->where('name', $request->device_name)->delete();
        // Create a new Sanctum personal access token for the user
        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful!',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'student_id' => $user->student_id,
                    'email' => $user->email,
                    'role' => $user->role->name ?? null,
                ]
            ]
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
}

<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{

    public function show(Request $request)
    {
        if (! $request->user()->tokenCan('admin:*') && ! $request->user()->tokenCan(['profile:view'])) {
            abort(403, 'Unauthorized. You do not have permission.');
        }
        $user = $request->user();

        return (new UserResource($user->load('role')))
            ->additional([
                'success' => true,
            ]);
    }

    public function update(Request $request)
    {
        if (! $request->user()->tokenCan('admin:*') && ! $request->user()->tokenCan('profile:update')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
            'username'   => ['required', 'string', 'max:255', 'unique:users,username,' . $request->user()->id],
            'avatar'     => ['nullable', 'image', 'max:2048'],
            'student_id' => ['nullable', 'string', 'max:255', 'unique:users,student_id,' . $request->user()->id],
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->first_name = $validated['first_name'];
        $user->last_name  = $validated['last_name'];
        $user->email      = $validated['email'];
        $user->username   = $validated['username'];
        $user->avatar     = $validated['avatar'] ?? $user->avatar;
        $user->student_id = $validated['student_id'] ?? $user->student_id;
        $user->save();

        return (new UserResource($user->load('role')))
            ->additional([
                'success' => true,
                'message' => 'Profile updated successfully.',
            ]);
    }

    public function destroy(Request $request)
    {
        if (! $request->user()->tokenCan('admin:*') && ! $request->user()->tokenCan('profile:update')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $user->currentAccessToken()->delete();

        $user->delete();

        return response()->json([null], 204);
    }
}

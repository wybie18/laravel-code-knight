<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!$request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }
        $user = $request->user();
        $query = User::where('id', '!=', $user->id);

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', "%{$searchTerm}%")
                ->orWhere('last_name', 'like', "%{$searchTerm}%")
                ->orWhere('username', 'like', "%{$searchTerm}%")
                ->orWhere('email', 'like', "%{$searchTerm}%");
            });
        }

        $sortField     = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");

        $query->orderBy($sortField, $sortDirection);

        $users = $query->paginate(15);
        $users->load('role');

        return UserResource::collection($users)->additional([
            'success' => true]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'username'     => 'required|string|max:255|unique:users,username',
            'student_id'   => 'nullable|string|max:255|unique:users,student_id',
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'required|string|max:255',
            'email'        => 'required|email|max:255|unique:users,email',
            'password'     => 'required|string|min:8|confirmed',
            'avatar'       => 'nullable|image|max:2048',
            'role_id'      => 'required|exists:user_roles,id',
        ]);

        if (!$request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $validated['password'] = bcrypt($validated['password']);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create($validated);

        return (new UserResource($user))
            ->additional([
                'success' => true,
                'message' => 'User created successfully.',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id)->load('role');

        return (new UserResource($user))
            ->additional([
                'success' => true,
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'username'     => 'sometimes|required|string|max:255|unique:users,username,' . $user->id,
            'student_id'   => 'nullable|string|max:255|unique:users,student_id,' . $user->id,
            'first_name'   => 'sometimes|required|string|max:255',
            'last_name'    => 'sometimes|required|string|max:255',
            'email'        => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'old_password' => 'nullable|string|min:8',
            'password'     => 'nullable|string|min:8|confirmed',
            'avatar'       => 'nullable|image|max:2048',
            'role_id'      => 'required|exists:user_roles,id',
        ]);

        if (!$request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        if (isset($validated['password'])) {
            if (!password_verify($request->input('old_password'), $user->password)) {
                abort(403, 'Old password does not match.');
            }
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($validated);
        $user->load('role');

        return (new UserResource($user))
            ->additional([
                'success' => true,
                'message' => 'User updated successfully.',
            ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (!request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $user = User::findOrFail($id);

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}

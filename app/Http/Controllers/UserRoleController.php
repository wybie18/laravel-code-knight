<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => UserRole::withCount('users')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:user_roles,name',
        ]);

        $userRole = UserRole::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'User role created successfully.',
            'data' => $userRole,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(UserRole $userRole)
    {
        return response()->json([
            'success' => true,
            'data' => $userRole->loadCount('users'),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UserRole $userRole)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:user_roles,name,' . $userRole->id,
        ]);

        $userRole->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully.',
            'data' => $userRole,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UserRole $userRole)
    {
        $userRole->delete();

        return response()->json(null, 204);
    }
}

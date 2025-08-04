<?php
namespace App\Http\Controllers;

use App\Http\Resources\UserRoleResource;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = UserRole::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");
            });
        }

        $sortField     = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");

        $query->orderBy($sortField, $sortDirection);

        $userRoles = $query->withCount('users')->paginate(15);
        return UserRoleResource::collection($userRoles)->additional([
            'success' => true]);
    }

    public function getUserRoles()
    {
        $userRoles = UserRole::select('id', 'name')->get();

        return UserRoleResource::collection($userRoles)->additional([
            'success' => true,
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

        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $userRole = UserRole::create($validated);
        $userRole->loadCount('users');

        return (new UserRoleResource($userRole))
            ->additional([
                'success' => true,
                'message' => 'User role created successfully.',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(String $id)
    {
        if (! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }
        $userRole = UserRole::findOrFail($id);

        return (new UserRoleResource($userRole->loadCount('users')))
            ->additional([
                'success' => true,
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, String $id)
    {
        $userRole  = UserRole::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:user_roles,name,' . $userRole->id,
        ]);

        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $userRole->update($validated);
        $userRole->loadCount('users');

        return (new UserRoleResource($userRole))
            ->additional([
                'success' => true,
                'message' => 'User role updated successfully.',
            ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(String $id)
    {
        if (! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $userRole = UserRole::findOrFail($id);
        $userRole->delete();

        return response()->json(null, 204);
    }
}

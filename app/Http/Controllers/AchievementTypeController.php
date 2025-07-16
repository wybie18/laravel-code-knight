<?php

namespace App\Http\Controllers;

use App\Models\AchievementType;
use Illuminate\Http\Request;

class AchievementTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AchievementType::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");
            });
        }

        $sortField     = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");

        $query->orderBy($sortField, $sortDirection);

        $achievementTypes = $query->withCount('achievements')->paginate(15);
        return response()->json([
            'success' => true,
            'data' => $achievementTypes,
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        if(!$request->user()->tokenCan('admin:*')){
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:achievement_types,name',
            'color' => 'nullable|string|max:7',
        ]);

        $achievementType = AchievementType::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Achievement type created successfully.',
            'data' => $achievementType,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $achievementType = AchievementType::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $achievementType->loadCount('achievements'),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if(!$request->user()->tokenCan('admin:*')){
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $achievementType = AchievementType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:achievement_types,name,' . $achievementType->id,
            'color' => 'nullable|string|max:7',
        ]);

        $achievementType->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Achievement type updated successfully.',
            'data' => $achievementType,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if(!request()->user()->tokenCan('admin:*')){
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $achievementType = AchievementType::findOrFail($id);

        $achievementType->delete();

        return response()->json(null, 204);
    }
}

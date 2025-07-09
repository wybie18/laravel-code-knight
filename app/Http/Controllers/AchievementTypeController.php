<?php

namespace App\Http\Controllers;

use App\Models\AchievementType;
use Illuminate\Http\Request;

class AchievementTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => AchievementType::withCount('achievements')->get(),
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
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
    public function show(AchievementType $achievementType)
    {
        return response()->json([
            'success' => true,
            'data' => $achievementType->loadCount('achievements'),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AchievementType $achievementType)
    {
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
    public function destroy(AchievementType $achievementType)
    {
        $achievementType->delete();

        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\BadgeCategory;
use Illuminate\Http\Request;

class BadgeCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => BadgeCategory::withCount('badges')->get(),
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:badge_categories,name',
        ]);

        $badgeCategory = BadgeCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Badge category created successfully.',
            'data' => $badgeCategory,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(BadgeCategory $badgeCategory)
    {
        return response()->json([
            'success' => true,
            'data' => $badgeCategory->loadCount('badges'),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BadgeCategory $badgeCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:badge_categories,name,' . $badgeCategory->id
        ]);

        $badgeCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Badge category updated successfully.',
            'data' => $badgeCategory,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BadgeCategory $badgeCategory)
    {
        $badgeCategory->delete();

        return response()->json(null, 204);
    }
}

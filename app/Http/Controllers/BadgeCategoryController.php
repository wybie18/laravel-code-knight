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
        if(!$request->user()->tokenCan('admin:*')){
            abort(403, 'Unauthorized. You do not have permission.');
        }

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
    public function show(string $id)
    {
        $badgeCategory = BadgeCategory::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $badgeCategory->loadCount('badges'),
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

        $badgeCategory = BadgeCategory::findOrFail($id);

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
    public function destroy(string $id)
    {
        if(!request()->user()->tokenCan('admin:*')){
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $badgeCategory = BadgeCategory::findOrFail($id);
        $badgeCategory->delete();

        return response()->json(null, 204);
    }
}

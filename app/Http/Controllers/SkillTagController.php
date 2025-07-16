<?php

namespace App\Http\Controllers;

use App\Models\SkillTag;
use Illuminate\Http\Request;

class SkillTagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = SkillTag::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");
            });
        }

        $sortField     = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");

        $query->orderBy($sortField, $sortDirection);

        $skillTags = $query->withCount('courses')->paginate(15);
        return response()->json([
            'success' => true,
            'data' => $skillTags,
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
            'name' => 'required|string|max:255|unique:skill_tags,name',
            'color' => 'nullable|string|max:7',
        ]);

        $skillTag = SkillTag::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Skill tag created successfully.',
            'data' => $skillTag,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $skillTag = SkillTag::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $skillTag->loadCount('courses'),
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

        $skillTag = SkillTag::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:skill_tags,name,' . $skillTag->id,
            'color' => 'nullable|string|max:7',
        ]);

        $skillTag->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Skill tag updated successfully.',
            'data' => $skillTag,
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

        $skillTag = SkillTag::findOrFail($id);
        $skillTag->delete();

        return response()->json(null, 204);
    }
}

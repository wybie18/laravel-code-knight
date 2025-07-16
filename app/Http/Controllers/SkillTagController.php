<?php

namespace App\Http\Controllers;

use App\Http\Resources\SkillTagResource;
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
        return SkillTagResource::collection($skillTags)->additional([
            'success' => true]);
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
        $skillTag->loadCount('courses');
        return (new SkillTagResource($skillTag))
            ->additional([
                'success' => true,
                'message' => 'Skill tag created successfully.',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $skillTag = SkillTag::findOrFail($id)->loadCount('courses');
        return (new SkillTagResource($skillTag))
            ->additional([
                'success' => true,
            ]);
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
        $skillTag->loadCount('courses');
        return (new SkillTagResource($skillTag))
            ->additional([
                'success' => true,
                'message' => 'Skill tag updated successfully.',
            ]);
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

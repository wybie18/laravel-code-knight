<?php

namespace App\Http\Controllers;

use App\Http\Resources\AchievementTypeResource;
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
        return AchievementTypeResource::collection($achievementTypes)->additional([
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
            'name' => 'required|string|max:255|unique:achievement_types,name',
        ]);

        $achievementType = AchievementType::create($validated);
        $achievementType->loadCount('achievements');

        return (new AchievementTypeResource($achievementType))
            ->additional([
                'success' => true,
                'message' => 'Achievement type created successfully.',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $achievementType = AchievementType::findOrFail($id)->loadCount('achievements');
        return (new AchievementTypeResource($achievementType))
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

        $achievementType = AchievementType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:achievement_types,name,' . $achievementType->id,
        ]);

        $achievementType->update($validated);
        $achievementType->loadCount('achievements');

        return (new AchievementTypeResource($achievementType))
            ->additional([
                'success' => true,
                'message' => 'Achievement type updated successfully.',
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

        $achievementType = AchievementType::findOrFail($id);

        $achievementType->delete();

        return response()->json(null, 204);
    }
}

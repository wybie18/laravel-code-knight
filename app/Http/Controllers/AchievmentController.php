<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AchievmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Achievement::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        $achievements = $query->with('type')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $achievements,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:achievements,name',
            'description' => 'required|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'type_id' => 'required|exists:achievement_types,id',
            'exp_reward' => 'nullable|integer|min:0',
            'requirements' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $count = Achievement::where('slug', 'like', $validated['slug'] . '%')->count();
        if ($count > 0) {
            $validated['slug'] = $validated['slug'] . '-' . ($count + 1);
        }

        $achievement = Achievement::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Achievement created successfully.',
            'data' => $achievement->load('type'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Achievement $achievement)
    {
        $achievement->load('type');
        
        return response()->json([
            'success' => true,
            'data' => $achievement,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Achievement $achievement)
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('achievements')->ignore($achievement->id),
            ],
            'description' => 'sometimes|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'type_id' => 'sometimes|exists:achievement_types,id',
            'exp_reward' => 'nullable|integer|min:0',
            'requirements' => 'nullable|array',
        ]);

        if ($request->has('name')) {
            $validated['slug'] = Str::slug($validated['name']);

            $count = Achievement::where('slug', 'like', $validated['slug'] . '%')
                ->whereNot('id', $achievement->id)->count();
            if ($count > 0) {
                $validated['slug'] = $validated['slug'] . '-' . ($count + 1);
            }
        }

        $achievement->update($validated);

        return response()->json($achievement);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Achievement $achievement)
    {
        $achievement->delete();

        return response()->json(null, 204);
    }
}

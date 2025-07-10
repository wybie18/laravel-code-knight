<?php

namespace App\Http\Controllers;

use App\Models\Level;
use Illuminate\Http\Request;

class LevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Level::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'level_number' => 'required|integer|unique:levels,level_number',
            'name' => 'required|string|max:255',
            'exp_required' => 'required|integer',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $level = Level::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Level created successfully.',
            'data' => $level,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Level $level)
    {
        return response()->json([
            'success' => true,
            'data' => $level,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Level $level)
    {
        $validated = $request->validate([
            'level_number' => 'required|integer|unique:levels,level_number,' . $level->id,
            'name' => 'required|string|max:255',
            'exp_required' => 'required|integer',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $level->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Level updated successfully.',
            'data' => $level,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Level $level)
    {
        $level->delete();

        return response()->json(null, 204);
    }
}

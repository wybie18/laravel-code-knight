<?php

namespace App\Http\Controllers;

use App\Models\CtfCategory;
use Illuminate\Http\Request;

class CtfCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => CtfCategory::withCount('ctfChallenges')->get(),
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
            'name' => 'required|string|max:255|unique:ctf_categories,name',
            'color' => 'nullable|string|max:7',
        ]);

        $ctfCategory = CtfCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'CTF category created successfully.',
            'data' => $ctfCategory,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $ctfCategory = CtfCategory::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $ctfCategory->loadCount('ctfChallenges'),
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

        $ctfCategory = CtfCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:ctf_categories,name,' . $ctfCategory->id,
            'color' => 'nullable|string|max:7',
        ]);

        $ctfCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'CTF category updated successfully.',
            'data' => $ctfCategory,
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

        $ctfCategoty = CtfCategory::findOrFail($id);

        $ctfCategoty->delete();

        return response()->json(null, 204);
    }
}

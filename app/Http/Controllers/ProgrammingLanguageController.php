<?php

namespace App\Http\Controllers;

use App\Models\ProgrammingLanguage;
use Illuminate\Http\Request;

class ProgrammingLanguageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => ProgrammingLanguage::all(),
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:programming_languages,name',
            'langueage_id' => 'required|integer|unique:programming_languages,langueage_id',
            'version' => 'nullable|string|max:50'
        ]);

        $programmingLanguage = ProgrammingLanguage::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Programming language created successfully.',
            'data' => $programmingLanguage,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ProgrammingLanguage $programmingLanguage)
    {
        return response()->json([
            'success' => true,
            'data' => $programmingLanguage,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProgrammingLanguage $programmingLanguage)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:programming_languages,name,' . $programmingLanguage->id,
            'langueage_id' => 'required|integer|unique:programming_languages,langueage_id,' . $programmingLanguage->id,
            'version' => 'nullable|string|max:50'
        ]);

        $programmingLanguage->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Programming language updated successfully.',
            'data' => $programmingLanguage,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProgrammingLanguage $programmingLanguage)
    {
        $programmingLanguage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Programming language deleted successfully.',
        ], 200);
    }
}

<?php
namespace App\Http\Controllers;

use App\Models\ProgrammingLanguage;
use Illuminate\Http\Request;

class ProgrammingLanguageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProgrammingLanguage::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('version', 'like', "%{$searchTerm}");
            });
        }

        $sortField     = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");

        $query->orderBy($sortField, $sortDirection);

        $programmingLanguages = $query->paginate(15);
        return response()->json([
            'success' => true,
            'data'    => $programmingLanguages,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:programming_languages,name',
            'language_id' => 'required|integer|unique:programming_languages,language_id',
            'version'     => 'nullable|string|max:50',
        ]);

        $programmingLanguage = ProgrammingLanguage::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Programming language created successfully.',
            'data'    => $programmingLanguage,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $programmingLanguage = ProgrammingLanguage::findOrFail($id);
        return response()->json([
            'success' => true,
            'data'    => $programmingLanguage,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $programmingLanguage = ProgrammingLanguage::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:programming_languages,name,' . $programmingLanguage->id,
            'language_id' => 'required|integer|unique:programming_languages,language_id,' . $programmingLanguage->id,
            'version'     => 'nullable|string|max:50',
        ]);

        $programmingLanguage->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Programming language updated successfully.',
            'data'    => $programmingLanguage,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $programmingLanguage = ProgrammingLanguage::findOrFail($id);
        $programmingLanguage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Programming language deleted successfully.',
        ], 200);
    }
}

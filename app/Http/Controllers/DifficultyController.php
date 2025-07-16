<?php
namespace App\Http\Controllers;

use App\Models\Difficulty;
use Illuminate\Http\Request;

class DifficultyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Difficulty::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");
            });
        }

        $sortField     = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");

        $query->orderBy($sortField, $sortDirection);

        $difficulties = $query->paginate(15);
        return response()->json([
            'success' => true,
            'data'    => $difficulties,
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
            'name' => 'required|string|max:255',
        ]);

        $difficulty = Difficulty::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Difficulty created successfully.',
            'data'    => $difficulty,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $difficulty = Difficulty::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $difficulty,
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

        $difficulty = Difficulty::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $difficulty->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Difficulty updated successfully.',
            'data'    => $difficulty,
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

        $difficulty = Difficulty::findOrFail($id);
        $difficulty->delete();

        return response()->json(null, 204);
    }
}

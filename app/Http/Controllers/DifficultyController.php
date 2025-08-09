<?php
namespace App\Http\Controllers;

use App\Http\Resources\DifficultyResource;
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
        return DifficultyResource::collection($difficulties)->additional([
            'success' => true]);
    }

    public function getDifficulties()
    {
        $difficulties = Difficulty::select('id', 'name')->get();

        return DifficultyResource::collection($difficulties)->additional([
            'success' => true,
        ]);
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

        return (new DifficultyResource($difficulty))
            ->additional([
                'success' => true,
                'message' => 'Difficulty created successfully.',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $difficulty = Difficulty::findOrFail($id);

        return (new DifficultyResource($difficulty))
            ->additional([
                'success' => true,
            ]);
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

        return (new DifficultyResource($difficulty))
            ->additional([
                'success' => true,
                'message' => 'Difficulty updated successfully.',
            ]);
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

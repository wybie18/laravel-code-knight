<?php
namespace App\Http\Controllers;

use App\Http\Resources\LevelResource;
use App\Models\Level;
use Illuminate\Http\Request;

class LevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $query = Level::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        $sortField     = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");

        $query->orderBy($sortField, $sortDirection);

        $levels = $query->paginate(15);

        return LevelResource::collection($levels)->additional([
            'success' => true]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'level_number' => 'required|integer|unique:levels,level_number',
            'name'         => 'required|string|max:255',
            'exp_required' => 'required|integer',
            'icon'         => 'nullable|string|max:255',
            'description'  => 'nullable|string',
        ]);

        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $level = Level::create($validated);

        return (new LevelResource($level))
            ->additional([
                'success' => true,
                'message' => 'Level created successfully.',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }
        
        $level = Level::findOrFail($id);
        return (new LevelResource($level))
            ->additional([
                'success' => true
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $level = Level::findOrFail($id);

        $validated = $request->validate([
            'level_number' => 'required|integer|unique:levels,level_number,' . $level->id,
            'name'         => 'required|string|max:255',
            'exp_required' => 'required|integer',
            'icon'         => 'nullable|string|max:255',
            'description'  => 'nullable|string',
        ]);

        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $level->update($validated);

        return (new LevelResource($level))
            ->additional([
                'success' => true,
                'message' => 'Level updated successfully.',
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

        $level = Level::findOrFail($id);
        $level->delete();

        return response()->json(null, 204);
    }
}

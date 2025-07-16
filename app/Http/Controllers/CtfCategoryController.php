<?php
namespace App\Http\Controllers;

use App\Http\Resources\CtfCategoryResource;
use App\Models\CtfCategory;
use Illuminate\Http\Request;

class CtfCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = CtfCategory::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");
            });
        }

        $sortField     = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");

        $query->orderBy($sortField, $sortDirection);

        $ctfCategory = $query->withCount('ctfChallenges')->paginate(15);

        return CtfCategoryResource::collection($ctfCategory)->additional([
            'success' => true]);
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
            'name'  => 'required|string|max:255|unique:ctf_categories,name',
            'color' => 'nullable|string|max:7',
        ]);

        $ctfCategory = CtfCategory::create($validated);
        $ctfCategory->loadCount('ctfChallenges');

        return (new CtfCategoryResource($ctfCategory))
            ->additional([
                'success' => true,
                'message' => 'CTF category created successfully.',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $ctfCategory = CtfCategory::findOrFail($id)->loadCount('ctfChallenges');
        return (new CtfCategoryResource($ctfCategory))
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

        $ctfCategory = CtfCategory::findOrFail($id);

        $validated = $request->validate([
            'name'  => 'required|string|max:255|unique:ctf_categories,name,' . $ctfCategory->id,
            'color' => 'nullable|string|max:7',
        ]);

        $ctfCategory->update($validated);
        $ctfCategory->loadCount('ctfChallenges');

        return (new CtfCategoryResource($ctfCategory))
            ->additional([
                'success' => true,
                'message' => 'CTF category updated successfully.',
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

        $ctfCategoty = CtfCategory::findOrFail($id);

        $ctfCategoty->delete();

        return response()->json(null, 204);
    }
}

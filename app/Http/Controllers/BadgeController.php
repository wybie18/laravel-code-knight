<?php
namespace App\Http\Controllers;

use App\Models\Badge;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BadgeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Badge::query();
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }
        $badges = $query->with('category')->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $badges,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255|unique:badges,name',
            'description'  => 'required|string',
            'icon'         => 'nullable|string|max:255',
            'color'        => 'nullable|string|max:7',
            'category_id'  => 'required|exists:badge_categories,id',
            'exp_reward'   => 'nullable|integer|min:0',
            'requirements' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $count = Badge::where('slug', 'like', $validated['slug'] . '%')->count();
        if ($count > 0) {
            $validated['slug'] = $validated['slug'] . '-' . ($count + 1);
        }

        $badge = Badge::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Badge created successfully.',
            'data'    => $badge,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Badge $badge)
    {
        return response()->json([
            'success' => true,
            'data'    => $badge->load('category'),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Badge $badge)
    {
        $validated = $request->validate([
            'name'         => [
                'required',
                'string',
                'max:255',
                Rule::unique('badges')->ignore($badge->id),
            ],
            'description'  => 'required|string',
            'icon'         => 'nullable|string|max:255',
            'color'        => 'nullable|string|max:7',
            'category_id'  => 'required|exists:badge_categories,id',
            'exp_reward'   => 'nullable|integer|min:0',
            'requirements' => 'nullable|array',
        ]);

        if ($request->has('name')) {
            $validated['slug'] = Str::slug($validated['name']);

            $count = Badge::where('slug', 'like', $validated['slug'] . '%')
                ->whereNot('id', $badge->id)->count();
            if ($count > 0) {
                $validated['slug'] = $validated['slug'] . '-' . ($count + 1);
            }
        }

        $badge->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Badge updated successfully.',
            'data'    => $badge,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Badge $badge)
    {
        $badge->delete();

        return response()->json(null, 204);
    }
}

<?php
namespace App\Http\Controllers;

use App\Http\Resources\BadgeResource;
use App\Models\Badge;
use App\Models\BadgeCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BadgeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }
        
        $query = Badge::query();
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }
        $sortField     = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");

        $badges = $query->orderBy($sortField, $sortDirection)->with('category')->paginate(15);

        return BadgeResource::collection($badges)->additional([
            'success' => true]);
    }
    
    public function getBadgeCategories(){

        return [
            'success' => true,
            'data' => BadgeCategory::all()
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255|unique:badges,name',
            'description'  => 'required|string',
            'icon'         => 'required|mimes:svg,png,jpg,jpeg,gif|max:2048',
            'color'        => 'nullable|string|max:7',
            'category_id'  => 'required|exists:badge_categories,id',
            'exp_reward'   => 'nullable|integer|min:0',
            'requirements' => 'nullable|json',
        ]);

        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $validated['requirements'] = json_decode($validated['requirements'], true);

        $validated['slug'] = Str::slug($validated['name']);

        $count = Badge::where('slug', 'like', $validated['slug'] . '%')->count();
        if ($count > 0) {
            $validated['slug'] = $validated['slug'] . '-' . ($count + 1);
        }

        $iconPath = null;
        if ($request->hasFile('icon')) {
            $iconPath = $request->file('icon')->store('badge_icons', 'public');
        }

        unset($validated['icon']);

        $badgeData = array_merge($validated, ['icon' => $iconPath]);

        $badge = Badge::create($badgeData);
        $badge->load('category');

        return (new BadgeResource($badge))
            ->additional([
                'success' => true,
                'message' => 'Badge created successfully.',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Badge $badge)
    {
        if (! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        return (new BadgeResource($badge->load('category')))
            ->additional([
                'success' => true,
            ]);
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
            'icon'         => 'nullable|mimes:svg,png,jpg,jpeg,gif|max:2048',
            'color'        => 'nullable|string|max:7',
            'category_id'  => 'required|exists:badge_categories,id',
            'exp_reward'   => 'nullable|integer|min:0',
            'requirements' => 'nullable|json',
        ]);

        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $validated['requirements'] = json_decode($validated['requirements'], true);

        if ($request->has('name')) {
            $validated['slug'] = Str::slug($validated['name']);

            $count = Badge::where('slug', 'like', $validated['slug'] . '%')
                ->whereNot('id', $badge->id)->count();
            if ($count > 0) {
                $validated['slug'] = $validated['slug'] . '-' . ($count + 1);
            }
        }

        $iconPath = $badge->icon;

        if ($request->hasFile('icon')) {
            if ($badge->icon && Storage::disk('public')->exists($badge->icon)) {
                Storage::disk('public')->delete($badge->icon);
            }
            $iconPath = $request->file('icon')->store('badge_icons', 'public');
        }

        unset($validated['icon']);

        $badge->update(array_merge($validated, ['icon' => $iconPath]));
        $badge->load('category');

        return (new BadgeResource($badge))
            ->additional([
                'success' => true,
                'message' => 'Badge updated successfully.',
            ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Badge $badge)
    {
        if (! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        if ($badge->icon && Storage::disk('public')->exists($badge->icon)) {
            Storage::disk('public')->delete($badge->icon);
        }

        $badge->delete();

        return response()->json(null, 204);
    }
}

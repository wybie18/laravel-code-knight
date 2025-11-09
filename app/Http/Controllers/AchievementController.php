<?php
namespace App\Http\Controllers;

use App\Http\Resources\AchievementResource;
use App\Models\Achievement;
use App\Models\AchievementType;
use App\Services\AchievementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AchievementController extends Controller
{
    protected $achievementService;

    public function __construct(AchievementService $achievementService)
    {
        $this->achievementService = $achievementService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $query = Achievement::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        $sortField     = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");

        $achievements = $query->orderBy($sortField, $sortDirection)->with('type')->paginate(15);

        return AchievementResource::collection($achievements)->additional([
            'success' => true]);
    }

    public function getAchievementTypes(){

        return [
            'success' => true,
            'data' => AchievementType::all()
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255|unique:achievements,name',
            'description'  => 'required|string',
            'icon'         => 'required|mimes:svg,png,jpg,jpeg,gif|max:2048',
            'color'        => 'nullable|string|max:7',
            'type_id'      => 'required|exists:achievement_types,id',
            'exp_reward'   => 'nullable|integer|min:0',
            'requirements' => 'nullable|json',
        ]);

        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $validated['requirements'] = json_decode($validated['requirements'], true);

        $validated['slug'] = Str::slug($validated['name']);

        $count = Achievement::where('slug', 'like', $validated['slug'] . '%')->count();
        if ($count > 0) {
            $validated['slug'] = $validated['slug'] . '-' . ($count + 1);
        }

        $iconPath = null;
        if ($request->hasFile('icon')) {
            $iconPath = $request->file('icon')->store('achievement_icons', 'public');
        }

        unset($validated['icon']);

        $achievement = Achievement::create(array_merge($validated, ['icon' => $iconPath]));
        $achievement->load('type');

        return (new AchievementResource($achievement))
            ->additional([
                'success' => true,
                'message' => 'Achievement created successfully.',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Achievement $achievement)
    {
        if (! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $achievement->load('type');

        return (new AchievementResource($achievement))
            ->additional([
                'success' => true,
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Achievement $achievement)
    {
        $validated = $request->validate([
            'name'         => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('achievements')->ignore($achievement->id),
            ],
            'description'  => 'sometimes|string',
            'icon'         => 'nullable|mimes:svg,png,jpg,jpeg,gif|max:2048',
            'color'        => 'nullable|string|max:7',
            'type_id'      => 'sometimes|exists:achievement_types,id',
            'exp_reward'   => 'nullable|integer|min:0',
            'requirements' => 'nullable|json',
        ]);

        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $validated['requirements'] = json_decode($validated['requirements'], true);

        if ($request->has('name')) {
            $validated['slug'] = Str::slug($validated['name']);

            $count = Achievement::where('slug', 'like', $validated['slug'] . '%')
                ->whereNot('id', $achievement->id)->count();
            if ($count > 0) {
                $validated['slug'] = $validated['slug'] . '-' . ($count + 1);
            }
        }

        $iconPath = $achievement->icon;

        if ($request->hasFile('icon')) {
            if ($achievement->icon && Storage::disk('public')->exists($achievement->icon)) {
                Storage::disk('public')->delete($achievement->icon);
            }
            $iconPath = $request->file('icon')->store('achievement_icons', 'public');
        }

        unset($validated['icon']);

        $achievement->update(array_merge($validated, ['icon' => $iconPath]));
        $achievement->load('type');

        return (new AchievementResource($achievement))
            ->additional([
                'success' => true,
                'message' => 'Achievement updated successfully.',
            ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Achievement $achievement)
    {
        if (! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        if ($achievement->icon && Storage::disk('public')->exists($achievement->icon)) {
            Storage::disk('public')->delete($achievement->icon);
        }

        $achievement->delete();

        return response()->json(null, 204);
    }

    /**
     * Get all achievements with user's progress
     * This returns all achievements and shows which ones the user has earned
     * and their progress towards unearned ones.
     */
    public function myAchievementsWithProgress(Request $request)
    {
        $user = $request->user();
        
        $achievementsWithProgress = $this->achievementService->getUserAchievementsWithProgress($user);

        return response()->json([
            'success' => true,
            'data' => [
                'achievements' => $achievementsWithProgress,
                'stats' => $this->achievementService->getUserAchievementStats($user),
            ]
        ]);
    }

    /**
     * Get user's achievement statistics
     */
    public function myAchievementStats(Request $request)
    {
        $user = $request->user();
        $stats = $this->achievementService->getUserAchievementStats($user);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get next achievement to unlock (for motivation)
     */
    public function nextToUnlock(Request $request)
    {
        $user = $request->user();
        $nextAchievement = $this->achievementService->getNextAchievementToUnlock($user);

        return response()->json([
            'success' => true,
            'data' => $nextAchievement
        ]);
    }

    /**
     * Get recently earned achievements
     */
    public function recentlyEarned(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:20'
        ]);

        $user = $request->user();
        $limit = $request->input('limit', 5);
        
        $recentAchievements = $this->achievementService->getRecentlyEarnedAchievements($user, $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'recent_achievements' => AchievementResource::collection($recentAchievements),
            ]
        ]);
    }
}

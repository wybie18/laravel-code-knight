<?php
namespace App\Http\Controllers;

use App\Services\AchievementService;
use App\Services\LevelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserStatsController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $levelService = app(LevelService::class);
        $levelInfo    = $levelService->getUserLevelInfo($user);

        return [
            'level'                 => $levelInfo,
            'courses_enrolled'      => $user->courseEnrollments()->count(),
            'courses_completed'     => $user->courseProgress()->whereNotNull('completed_at')->count(),
            'activities_completed'  => $user->activityProgress()->whereNotNull('completed_at')->count(),
            'achievements_earned'   => $user->achievements()->count(),
            'current_streak'        => $user->streaks()->latest()->first()->current_streak ?? 0,
            'longest_streak'        => $user->streaks()->latest()->first()->longest_streak ?? 0,
            'total_submissions'     => $user->activitySubmissions()->count(),
            'challenge_submissions' => $user->challengeSubmissions()->count(),
            'challenges_completed'  => $user->challengeSubmissions()->where('is_correct', true)->distinct('challenge_id')->count(),
        ];
    }

    public function getUserRank()
    {
        $user = Auth::user();
        $levelService = app(LevelService::class);
        $rankData = $levelService->getUserRankData($user);

        return [
            'rank'           => $rankData['rank'],
            'total_users'    => $rankData['total_users'],
            'top_percentage' => $rankData['top_percentage'],
        ];
    }

    public function getUserAchievements(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $user = Auth::user();

        $achievements = app(AchievementService::class)
            ->getUserAchievements($user, $validated['limit'] ?? null);

        return $achievements->map(function ($achievement) {
            return [
                'id'          => $achievement->id,
                'name'        => $achievement->name,
                'description' => $achievement->description,
                'icon'        => $achievement->icon ? url(Storage::url($achievement->icon)) : '',
                'exp_reward'  => $achievement->exp_reward,
                'type'        => $achievement->type?->name,
            ];
        });
    }

}

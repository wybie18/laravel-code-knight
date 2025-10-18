<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LevelService;
use Illuminate\Support\Facades\Auth;

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
            'badges_earned'         => $user->badges()->count(),
            'current_streak'        => $user->streaks()->latest()->first()->current_streak ?? 0,
            'longest_streak'        => $user->streaks()->latest()->first()->longest_streak ?? 0,
            'total_submissions'     => $user->activitySubmissions()->count(),
            'challenge_submissions' => $user->challengeSubmissions()->count(),
            'challenges_completed'  => $user->challengeSubmissions()->where('is_correct', true)->distinct('challenge_id')->count(),
        ];
    }
}

<?php
namespace App\Services;

use App\Events\AchievementEarned;
use App\Http\Resources\AchievementResource;
use App\Models\Achievement;
use App\Models\CodingChallenge;
use App\Models\CtfChallenge;
use App\Models\TypingChallenge;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AchievementService
{
    /**
     * Check if a user has earned a specific achievement
     */
    public function hasAchievement(User $user, Achievement $achievement): bool
    {
        return $user->achievements()->where('achievements.id', $achievement->id)->exists();
    }

    /**
     * Award an achievement to a user
     */
    public function awardAchievement(User $user, Achievement $achievement): ?UserAchievement
    {
        if ($this->hasAchievement($user, $achievement)) {
            return null;
        }

        return DB::transaction(function () use ($user, $achievement) {
            $userAchievement = $user->achievements()->attach($achievement->id, ['earned_at' => now()]);

            if ($achievement->exp_reward > 0) {
                $description = "Achievement xp reward for " . $achievement->title;
                app(LevelService::class)->addXp($user, $achievement->exp_reward, $description, $achievement);
            }

            event(new AchievementEarned($user, $achievement));

            return $userAchievement;
        });
    }

    /**
     * Check and award achievements based on requirements
     */
    public function checkAndAwardAchievements(User $user): Collection
    {
        $awarded      = collect();
        $achievements = Achievement::with('type')->get();

        foreach ($achievements as $achievement) {
            if ($this->hasAchievement($user, $achievement)) {
                continue;
            }

            if ($this->meetsRequirements($user, $achievement)) {
                $userAchievement = $this->awardAchievement($user, $achievement);
                if ($userAchievement) {
                    $awarded->push($achievement);
                }
            }
        }

        return $awarded;
    }

    /**
     * Check if user meets achievement requirements
     */
    public function meetsRequirements(User $user, Achievement $achievement): bool
    {
        if (empty($achievement->requirements)) {
            return false;
        }

        foreach ($achievement->requirements as $key => $value) {
            if (! $this->checkRequirement($user, $key, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check individual requirement
     */
    protected function checkRequirement(User $user, string $key, $value): bool
    {
        switch ($key) {
            case 'level':
                return $user->current_level >= $value;

            case 'exp':
            case 'total_xp':
                return $user->total_xp >= $value;

            case 'completed_courses':
                return $user->courseEnrollments()
                    ->whereNotNull('completed_at')
                    ->count() >= $value;

            case 'completed_lessons':
                return $user->lessonProgress()->count() >= $value;

            case 'completed_activities':
                return $user->activityProgress()
                    ->where('is_completed', true)
                    ->count() >= $value;

            case 'coding_challenges_solved':
                return $user->challengeSubmissions()
                    ->where('is_correct', true)
                    ->whereHas('challenge', function ($query) {
                        $query->where('challengeable_type', CodingChallenge::class);
                    })
                    ->distinct()
                    ->count('challenge_id') >= $value;

            case 'ctf_challenges_solved':
                return $user->challengeSubmissions()
                    ->where('is_correct', true)
                    ->whereHas('challenge', function ($query) {
                        $query->where('challengeable_type', CtfChallenge::class);
                    })
                    ->distinct()
                    ->count('challenge_id') >= $value;

            case 'typeing_tests_completed':
                return $user->challengeSubmissions()
                    ->where('is_correct', true)
                    ->whereHas('challenge', function ($query) {
                        $query->where('challengeable_type', TypingChallenge::class);
                    })
                    ->distinct()
                    ->count('challenge_id') >= $value;

            case 'total_challenges_solved':
                return $user->challengeSubmissions()
                    ->where('is_correct', true)
                    ->distinct()
                    ->count('challenge_id') >= $value;

            case 'achievements_count':
                return $user->achievements()->count() >= $value;

            case 'has_achievement':
                return $user->achievements()
                    ->where(function ($query) use ($value) {
                        $query->where('achievement_id', $value)
                            ->orWhereHas('achievement', function ($q) use ($value) {
                                $q->where('slug', $value);
                            });
                    })
                    ->exists();

            case 'user_activity':
                return app(UserActivityService::class)
                    ->wasActiveToday($user);

            case 'consecutive_days_active':
                return app(UserActivityService::class)
                    ->getCurrentStreak($user) >= $value;

            case 'longest_streak':
                return app(UserActivityService::class)
                    ->getLongestStreak($user) >= $value;

            case 'account_age_days':
                return $user->created_at->diffInDays(now()) >= $value;

            default:
                if (isset($user->$key)) {
                    return $user->$key >= $value;
                }
                return false;
        }
    }

    /**
     * Get all achievements for a user with progress
     */
    public function getUserAchievementsWithProgress(User $user): Collection
    {
        return Achievement::with('type')->get()->map(function ($achievement) use ($user) {
            $earned   = $this->hasAchievement($user, $achievement);
            $progress = $this->calculateProgress($user, $achievement);

            return [
                'achievement'         => new AchievementResource($achievement),
                'earned'              => $earned,
                'earned_at'           => $earned ? $user->achievements()->where('achievement_id', $achievement->id)->first()->pivot->created_at : null,
                'progress'            => $progress,
                'progress_percentage' => $this->calculateProgressPercentage($progress),
            ];
        });
    }

    /**
     * Calculate progress for an achievement
     */
    public function calculateProgress(User $user, Achievement $achievement): array
    {
        $progress = [];

        if (empty($achievement->requirements)) {
            return $progress;
        }

        foreach ($achievement->requirements as $key => $required) {
            $current        = $this->getCurrentValue($user, $key);
            $progress[$key] = [
                'current'   => $current,
                'required'  => $required,
                'completed' => $current >= $required,
            ];
        }

        return $progress;
    }

    /**
     * Get current value for a requirement
     */
    protected function getCurrentValue(User $user, string $key)
    {
        switch ($key) {
            case 'level':
                return $user->current_level ?? 0;

            case 'exp':
            case 'total_xp':
                return $user->total_xp ?? 0;

            case 'completed_courses':
                return $user->courseEnrollments()
                    ->whereNotNull('completed_at')
                    ->count();

            case 'completed_lessons':
                return $user->lessonProgress()->count();

            case 'completed_activities':
                return $user->activityProgress()
                    ->where('is_completed', true)
                    ->count();

            case 'coding_challenges_solved':
                return $user->challengeSubmissions()
                    ->where('is_correct', true)
                    ->whereHas('challenge', function ($query) {
                        $query->where('challengeable_type', CodingChallenge::class);
                    })
                    ->distinct()
                    ->count('challenge_id');

            case 'ctf_challenges_solved':
                return $user->challengeSubmissions()
                    ->where('is_correct', true)
                    ->whereHas('challenge', function ($query) {
                        $query->where('challengeable_type', CtfChallenge::class);
                    })
                    ->distinct()
                    ->count('challenge_id');

            case 'typeing_tests_completed':
                return $user->challengeSubmissions()
                    ->where('is_correct', true)
                    ->whereHas('challenge', function ($query) {
                        $query->where('challengeable_type', TypingChallenge::class);
                    })
                    ->distinct()
                    ->count('challenge_id');

            case 'total_challenges_solved':
                return $user->challengeSubmissions()
                    ->where('is_correct', true)
                    ->distinct()
                    ->count('challenge_id');

            case 'achievements_count':
                return $user->achievements()->count();

            case 'consecutive_days_active':
                return app(UserActivityService::class)
                    ->getCurrentStreak($user);

            case 'longest_streak':
                return app(UserActivityService::class)
                    ->getLongestStreak($user);

            case 'account_age_days':
                return $user->created_at->diffInDays(now());

            case 'enrolled_courses':
                return $user->courseEnrollments()->count();

            case 'active_courses':
                return $user->courseEnrollments()
                    ->where('status', 'active')
                    ->count();

            case 'modules_completed':
                return $user->moduleProgress()
                    ->whereNotNull('completed_at')
                    ->count();

            case 'exp_transactions':
                return $user->expTransactions()->count();

            case 'total_submissions':
                return $user->challengeSubmissions()->count();

            case 'user_activity':
                return app(UserActivityService::class)->wasActiveToday($user) ? 1 : 0;
            case 'has_achievement':
                return 1;

            default:
                return $user->$key ?? 0;
        }
    }

    /**
     * Calculate overall progress percentage
     */
    protected function calculateProgressPercentage(array $progress): float
    {
        if (empty($progress)) {
            return 0;
        }

        $total     = count($progress);
        $completed = collect($progress)->where('completed', true)->count();

        return round(($completed / $total) * 100, 2);
    }

    /**
     * Get achievements by type
     */
    public function getAchievementsByType(int $typeId): Collection
    {
        return Achievement::where('type_id', $typeId)->get();
    }

    /**
     * Get user's earned achievements
     */
    public function getUserAchievements(User $user, ?int $limit = null): Collection
    {
        $query = $user->achievements()
            ->with('type')
            ->orderByDesc('user_achievements.earned_at');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Revoke an achievement from a user
     */
    public function revokeAchievement(User $user, Achievement $achievement): bool
    {
        return DB::transaction(function () use ($user, $achievement) {
            if (! $user->achievements()->where('achievements.id', $achievement->id)->exists()) {
                return false;
            }

            // Remove EXP if it was awarded
            if ($achievement->exp_reward > 0) {
                $user->decrement('exp', $achievement->exp_reward);
            }

            $user->achievements()->detach($achievement->id);
            return true;
        });
    }

    /**
     * Get achievement statistics for a user
     */
    public function getUserAchievementStats(User $user): array
    {
        $totalAchievements  = Achievement::count();
        $earnedAchievements = $user->achievements()->count();
        $typeStats          = DB::table('user_achievements')
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->join('achievement_types', 'achievements.type_id', '=', 'achievement_types.id')
            ->where('user_achievements.user_id', $user->id)
            ->select('achievement_types.name', DB::raw('count(*) as count'))
            ->groupBy('achievement_types.id', 'achievement_types.name')
            ->get();

        return [
            'total_achievements'    => $totalAchievements,
            'earned_achievements'   => $earnedAchievements,
            'completion_percentage' => $totalAchievements > 0 ? round(($earnedAchievements / $totalAchievements) * 100, 2) : 0,
            'type_stats'            => $typeStats,
        ];
    }

    /**
     * Get recently earned achievements
     */
    public function getRecentlyEarnedAchievements(User $user, int $limit = 5): Collection
    {
        return $user->achievements()
            ->orderBy('user_achievements.created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get next achievement to unlock for motivation
     */
    public function getNextAchievementToUnlock(User $user): ?array
    {
        $achievements      = Achievement::all();
        $closest           = null;
        $closestPercentage = 0;

        foreach ($achievements as $achievement) {
            if ($this->hasAchievement($user, $achievement)) {
                continue;
            }

            $progress   = $this->calculateProgress($user, $achievement);
            $percentage = $this->calculateProgressPercentage($progress);

            if ($percentage > $closestPercentage) {
                $closestPercentage = $percentage;
                $closest           = [
                    'achievement'         => $achievement,
                    'progress'            => $progress,
                    'progress_percentage' => $percentage,
                ];
            }
        }

        return $closest;
    }
}

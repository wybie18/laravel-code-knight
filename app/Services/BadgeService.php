<?php
namespace App\Services;

use App\Models\Badge;
use App\Models\CodingChallenge;
use App\Models\CtfChallenge;
use App\Models\TypingChallenge;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    /**
     * Check if a user has earned a specific badge
     */
    public function hasBadge(User $user, Badge $badge): bool
    {
        return $user->badges()->where('badge_id', $badge->id)->exists();
    }

    /**
     * Award a badge to a user
     */
    public function awardBadge(User $user, Badge $badge): ?UserBadge
    {
        if ($this->hasBadge($user, $badge)) {
            return null;
        }

        return DB::transaction(function () use ($user, $badge) {
            $userBadge = UserBadge::create([
                'user_id'   => $user->id,
                'badge_id'  => $badge->id,
                'earned_at' => now(),
            ]);

            // Award EXP if available
            if ($badge->exp_reward > 0) {
                $user->increment('exp', $badge->exp_reward);
            }

            return $userBadge;
        });
    }

    /**
     * Check and award badges based on requirements
     */
    public function checkAndAwardBadges(User $user): Collection
    {
        $awarded = collect();
        $badges  = Badge::with('category')->get();

        foreach ($badges as $badge) {
            if ($this->hasBadge($user, $badge)) {
                continue;
            }

            if ($this->meetsRequirements($user, $badge)) {
                $userBadge = $this->awardBadge($user, $badge);
                if ($userBadge) {
                    $awarded->push($badge);
                }
            }
        }

        return $awarded;
    }

    /**
     * Check if user meets badge requirements
     */
    public function meetsRequirements(User $user, Badge $badge): bool
    {
        if (empty($badge->requirements)) {
            return false;
        }

        foreach ($badge->requirements as $key => $value) {
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
        // Handle different requirement types
        switch ($key) {
            case 'level':
                return $user->current_level >= $value;

            case 'exp':
            case 'total_xp':
                return $user->total_xp >= $value;

            case 'top_percentage':
                return false;
            
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

            case 'badges_count':
                return $user->badges()->count() >= $value;

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
     * Get all badges for a user with progress
     */
    public function getUserBadgesWithProgress(User $user): Collection
    {
        return Badge::with('category')->get()->map(function ($badge) use ($user) {
            $earned   = $this->hasBadge($user, $badge);
            $progress = $this->calculateProgress($user, $badge);

            return [
                'badge'               => $badge,
                'earned'              => $earned,
                'earned_at'           => $earned ? $user->badges()->where('badge_id', $badge->id)->first()->pivot->created_at : null,
                'progress'            => $progress,
                'progress_percentage' => $this->calculateProgressPercentage($progress),
            ];
        });
    }

    /**
     * Calculate progress for a badge
     */
    public function calculateProgress(User $user, Badge $badge): array
    {
        $progress = [];

        if (empty($badge->requirements)) {
            return $progress;
        }

        foreach ($badge->requirements as $key => $required) {
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

            case 'top_percentage':
                return false;
            
            case 'completed_courses':
                return $user->courseEnrollments()
                    ->whereNotNull('completed_at')
                    ->count() ?? 0;

            case 'completed_lessons':
                return $user->lessonProgress()->count() ?? 0;
            
            case 'completed_activities':
                return $user->activityProgress()
                    ->where('is_completed', true)
                    ->count() ?? 0;

            case 'coding_challenges_solved':
                return $user->challengeSubmissions()
                    ->where('is_correct', true)
                    ->whereHas('challenge', function ($query) {
                        $query->where('challengeable_type', CodingChallenge::class);
                    })
                    ->distinct()
                    ->count('challenge_id') ?? 0;
            
            case 'ctf_challenges_solved':
                return $user->challengeSubmissions()
                    ->where('is_correct', true)
                    ->whereHas('challenge', function ($query) {
                        $query->where('challengeable_type', CtfChallenge::class);
                    })
                    ->distinct()
                    ->count('challenge_id') ?? 0;
            
            case 'typeing_tests_completed':
                return $user->challengeSubmissions()
                    ->where('is_correct', true)
                    ->whereHas('challenge', function ($query) {
                        $query->where('challengeable_type', TypingChallenge::class);
                    })
                    ->distinct()
                    ->count('challenge_id') ?? 0;
            
            case 'total_challenges_solved':
                return $user->challengeSubmissions()
                    ->where('is_correct', true)
                    ->distinct()
                    ->count('challenge_id') ?? 0;

            case 'achievements_count':
                return $user->achievements()->count() ?? 0;

            case 'badges_count':
                return $user->badges()->count() ?? 0;

            case 'account_age_days':
                return $user->created_at->diffInDays(now()) ?? 0;

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
     * Get badges by category
     */
    public function getBadgesByCategory(int $categoryId): Collection
    {
        return Badge::where('category_id', $categoryId)->get();
    }

    /**
     * Get user's earned badges
     */
    public function getUserBadges(User $user): Collection
    {
        return $user->badges()->with('category')->get();
    }

    /**
     * Revoke a badge from a user
     */
    public function revokeBadge(User $user, Badge $badge): bool
    {
        return DB::transaction(function () use ($user, $badge) {
            $userBadge = UserBadge::where('user_id', $user->id)
                ->where('badge_id', $badge->id)
                ->first();

            if (! $userBadge) {
                return false;
            }

            if ($badge->exp_reward > 0) {
                $user->decrement('exp', $badge->exp_reward);
            }

            $userBadge->delete();
            return true;
        });
    }

    /**
     * Get badge statistics for a user
     */
    public function getUserBadgeStats(User $user): array
    {
        $totalBadges   = Badge::count();
        $earnedBadges  = $user->badges()->count();
        $categoryStats = DB::table('user_badges')
            ->join('badges', 'user_badges.badge_id', '=', 'badges.id')
            ->join('badge_categories', 'badges.category_id', '=', 'badge_categories.id')
            ->where('user_badges.user_id', $user->id)
            ->select('badge_categories.name', DB::raw('count(*) as count'))
            ->groupBy('badge_categories.id', 'badge_categories.name')
            ->get();

        return [
            'total_badges'          => $totalBadges,
            'earned_badges'         => $earnedBadges,
            'completion_percentage' => $totalBadges > 0 ? round(($earnedBadges / $totalBadges) * 100, 2) : 0,
            'category_stats'        => $categoryStats,
        ];
    }

    /**
     * Get recently earned badges
     */
    public function getRecentlyEarnedBadges(User $user, int $limit = 5): Collection
    {
        return $user->badges()
            ->orderBy('user_badges.created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}

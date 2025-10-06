<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AchievementService
{
    /**
     * Check if a user has earned a specific achievement
     */
    public function hasAchievement(User $user, Achievement $achievement): bool
    {
        return $user->achievements()->where('achievement_id', $achievement->id)->exists();
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
            $userAchievement = UserAchievement::create([
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'earned_at' => now(),
            ]);

            // Award EXP if available
            if ($achievement->exp_reward > 0) {
                $user->increment('exp', $achievement->exp_reward);
            }

            return $userAchievement;
        });
    }

    /**
     * Check and award achievements based on requirements
     */
    public function checkAndAwardAchievements(User $user): Collection
    {
        $awarded = collect();
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
            if (!$this->checkRequirement($user, $key, $value)) {
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
                return $user->level >= $value;
            
            case 'exp':
                return $user->exp >= $value;
            
            case 'posts_count':
                return $user->posts()->count() >= $value;
            
            case 'comments_count':
                return $user->comments()->count() >= $value;
            
            case 'likes_received':
                return $user->receivedLikes()->count() >= $value;
            
            case 'likes_given':
                return $user->likes()->count() >= $value;
            
            case 'followers_count':
                return $user->followers()->count() >= $value;
            
            case 'following_count':
                return $user->following()->count() >= $value;
            
            case 'badges_count':
                return $user->badges()->count() >= $value;
            
            case 'achievements_count':
                return $user->achievements()->count() >= $value;
            
            case 'has_badge':
                return $user->badges()->where('slug', $value)->exists();
            
            case 'has_achievement':
                return $user->achievements()->where('slug', $value)->exists();
            
            case 'consecutive_days_active':
                return $this->checkConsecutiveDaysActive($user) >= $value;
            
            case 'account_age_days':
                return $user->created_at->diffInDays(now()) >= $value;
            
            case 'profile_complete':
                return $this->isProfileComplete($user) === $value;
            
            case 'verified':
                return $user->verified === $value;
            
            default:
                if (isset($user->$key)) {
                    return $user->$key >= $value;
                }
                return false;
        }
    }

    /**
     * Check consecutive days active (placeholder - implement based on your activity tracking)
     */
    protected function checkConsecutiveDaysActive(User $user): int
    {
        // Implement based on your activity tracking system
        // Example: check login logs or activity timestamps
        return 0;
    }

    /**
     * Check if profile is complete
     */
    protected function isProfileComplete(User $user): bool
    {
        $requiredFields = ['name', 'email', 'bio', 'avatar'];
        
        foreach ($requiredFields as $field) {
            if (empty($user->$field)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get all achievements for a user with progress
     */
    public function getUserAchievementsWithProgress(User $user): Collection
    {
        return Achievement::with('type')->get()->map(function ($achievement) use ($user) {
            $earned = $this->hasAchievement($user, $achievement);
            $progress = $this->calculateProgress($user, $achievement);

            return [
                'achievement' => $achievement,
                'earned' => $earned,
                'earned_at' => $earned ? $user->achievements()->where('achievement_id', $achievement->id)->first()->pivot->created_at : null,
                'progress' => $progress,
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
            $current = $this->getCurrentValue($user, $key);
            $progress[$key] = [
                'current' => $current,
                'required' => $required,
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
                return $user->level ?? 0;
            
            case 'exp':
                return $user->exp ?? 0;
            
            case 'posts_count':
                return $user->posts()->count();
            
            case 'comments_count':
                return $user->comments()->count();
            
            case 'likes_received':
                return $user->receivedLikes()->count();
            
            case 'likes_given':
                return $user->likes()->count();
            
            case 'followers_count':
                return $user->followers()->count();
            
            case 'following_count':
                return $user->following()->count();
            
            case 'badges_count':
                return $user->badges()->count();
            
            case 'achievements_count':
                return $user->achievements()->count();
            
            case 'consecutive_days_active':
                return $this->checkConsecutiveDaysActive($user);
            
            case 'account_age_days':
                return $user->created_at->diffInDays(now());
            
            case 'profile_complete':
            case 'verified':
            case 'has_badge':
            case 'has_achievement':
                return 1; // Boolean check
            
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

        $total = count($progress);
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
    public function getUserAchievements(User $user): Collection
    {
        return $user->achievements()->with('type')->get();
    }

    /**
     * Revoke an achievement from a user
     */
    public function revokeAchievement(User $user, Achievement $achievement): bool
    {
        return DB::transaction(function () use ($user, $achievement) {
            $userAchievement = UserAchievement::where('user_id', $user->id)
                ->where('achievement_id', $achievement->id)
                ->first();

            if (!$userAchievement) {
                return false;
            }

            // Remove EXP if it was awarded
            if ($achievement->exp_reward > 0) {
                $user->decrement('exp', $achievement->exp_reward);
            }

            $userAchievement->delete();
            return true;
        });
    }

    /**
     * Get achievement statistics for a user
     */
    public function getUserAchievementStats(User $user): array
    {
        $totalAchievements = Achievement::count();
        $earnedAchievements = $user->achievements()->count();
        $typeStats = DB::table('user_achievements')
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->join('achievement_types', 'achievements.type_id', '=', 'achievement_types.id')
            ->where('user_achievements.user_id', $user->id)
            ->select('achievement_types.name', DB::raw('count(*) as count'))
            ->groupBy('achievement_types.id', 'achievement_types.name')
            ->get();

        return [
            'total_achievements' => $totalAchievements,
            'earned_achievements' => $earnedAchievements,
            'completion_percentage' => $totalAchievements > 0 ? round(($earnedAchievements / $totalAchievements) * 100, 2) : 0,
            'type_stats' => $typeStats,
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
        $achievements = Achievement::all();
        $closest = null;
        $closestPercentage = 0;

        foreach ($achievements as $achievement) {
            if ($this->hasAchievement($user, $achievement)) {
                continue;
            }

            $progress = $this->calculateProgress($user, $achievement);
            $percentage = $this->calculateProgressPercentage($progress);

            if ($percentage > $closestPercentage) {
                $closestPercentage = $percentage;
                $closest = [
                    'achievement' => $achievement,
                    'progress' => $progress,
                    'progress_percentage' => $percentage,
                ];
            }
        }

        return $closest;
    }
}
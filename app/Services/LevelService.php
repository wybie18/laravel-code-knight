<?php
namespace App\Services;

use App\Models\Level;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LevelService
{
    private const BASE_XP       = 100;
    private const XP_MULTIPLIER = 1.5;

    private const CACHE_KEY = 'level_milestones';

    private const CACHE_DURATION = 86400;

    /**
     * Get all level milestones (special named levels from database)
     * These are ranks/titles like "Novice", "Expert", "Master", etc.
     *
     * @return Collection
     */
    public function getLevelMilestones(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_DURATION, function () {
            return Level::orderBy('level_number')->get();
        });
    }

    /**
     * Calculate XP required for a specific level
     *
     * @param int $level
     * @return int
     */
    public function calculateXpForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        $totalXp = 0;
        for ($i = 1; $i < $level; $i++) {
            $totalXp += $this->calculateXpForNextLevel($i);
        }

        return $totalXp;
    }

    /**
     * Calculate XP required to advance from current level to next level
     *
     * @param int $currentLevel
     * @return int
     */
    public function calculateXpForNextLevel(int $currentLevel): int
    {
        return (int) round(self::BASE_XP * pow($currentLevel, self::XP_MULTIPLIER));
    }

    /**
     * Calculate level from total XP
     *
     * @param int $totalXp
     * @return int
     */
    public function calculateLevelFromXp(int $totalXp): int
    {
        $level    = 1;
        $xpNeeded = 0;

        while ($totalXp >= $xpNeeded + $this->calculateXpForNextLevel($level)) {
            $xpNeeded += $this->calculateXpForNextLevel($level);
            $level++;
        }

        return $level;
    }

    /**
     * Get user's current level information including progress to next level
     *
     * @param User $user
     * @return array
     */
    public function getUserLevelInfo(User $user): array
    {
        $currentLevel = $user->current_level ?? 1;
        $totalXp      = $user->total_xp ?? 0;

        $xpForCurrentLevel        = $this->calculateXpForLevel($currentLevel);
        $xpForNextLevel           = $this->calculateXpForLevel($currentLevel + 1);
        $xpNeededForNextLevel     = $xpForNextLevel - $xpForCurrentLevel;
        $xpProgressInCurrentLevel = $totalXp - $xpForCurrentLevel;
        $progressPercentage       = $xpNeededForNextLevel > 0
            ? round(($totalXp / $xpForNextLevel) * 100, 2)
            : 100;

        $milestone     = $this->getLevelMilestone($currentLevel);
        $nextMilestone = $this->getNextLevelMilestone($currentLevel);

        return [
            'current_level'                => $currentLevel,
            'total_xp'                     => $totalXp,
            'xp_for_current_level'         => $xpForCurrentLevel,
            'xp_for_next_level'            => $xpForNextLevel,
            'xp_needed_for_next_level'     => $xpNeededForNextLevel,
            'xp_progress_in_current_level' => $xpProgressInCurrentLevel,
            'progress_percentage'          => $progressPercentage,
            'current_milestone'            => $milestone,
            'next_milestone'               => $nextMilestone,
        ];
    }

    /**
     * Get the level milestone/title for a specific level
     *
     * @param int $level
     * @return array|null
     */
    public function getLevelMilestone(int $level): ?array
    {
        $milestones = $this->getLevelMilestones();

        // Find the highest milestone that the user has reached
        $milestone = $milestones
            ->where('level_number', '<=', $level)
            ->sortByDesc('level_number')
            ->first();

        if (! $milestone) {
            return null;
        }

        return [
            'level_number' => $milestone->level_number,
            'name'         => $milestone->name,
            'icon'         => $milestone->icon ? url(Storage::url($milestone->icon)) : '',
            'description'  => $milestone->description,
        ];
    }

    /**
     * Get the next level milestone/title that the user can achieve
     *
     * @param int $currentLevel
     * @return array|null
     */
    public function getNextLevelMilestone(int $currentLevel): ?array
    {
        $milestones = $this->getLevelMilestones();

        $nextMilestone = $milestones
            ->where('level_number', '>', $currentLevel)
            ->sortBy('level_number')
            ->first();

        if (! $nextMilestone) {
            return null;
        }

        return [
            'level_number' => $nextMilestone->level_number,
            'name'         => $nextMilestone->name,
            'icon'         => $nextMilestone->icon ? url(Storage::url($nextMilestone->icon)) : '',
            'description'  => $nextMilestone->description,
            'xp_required'  => $this->calculateXpForLevel($nextMilestone->level_number),
        ];
    }

    /**
     * Update user level based on their total XP
     * Returns true if user leveled up
     *
     * @param User $user
     * @return array
     */
    public function updateUserLevel(User $user): array
    {
        $oldLevel = $user->current_level ?? 1;
        $newLevel = $this->calculateLevelFromXp($user->total_xp);

        $leveledUp = $newLevel > $oldLevel;

        if ($leveledUp) {
            $user->current_level = $newLevel;
            $user->save();
            app(AchievementService::class)->checkAndAwardAchievements($user);
        }

        $levelInfo = $this->getUserLevelInfo($user);

        return [
            'leveled_up' => $leveledUp,
            'old_level'  => $oldLevel,
            'new_level'  => $newLevel,
            'level_info' => $levelInfo,
        ];
    }

    /**
     * Add XP to user and handle level up
     *
     * @param User $user
     * @param int $xpAmount
     * @param Model $sourceModel   // e.g., Activity, Quiz, Lesson, Challenge
     * @param string|null $customDescription
     * @return array
     */
    public function addXp(User $user, int $xpAmount, ?string $customDescription = null, Model $sourceModel): array
    {
        DB::beginTransaction();

        try {
            $oldXp = $user->total_xp ?? 0;

            $sourceType = get_class($sourceModel);
            $sourceId   = $sourceModel->id;

            $typeDescriptions = [
                'App\\Models\\Activity'  => 'Completed Activity',
                'App\\Models\\Quiz'      => 'Completed Quiz',
                'App\\Models\\Lesson'    => 'Finished Lesson',
                'App\\Models\\Challenge' => 'Solved Challenge',
            ];

            $baseDescription = $typeDescriptions[$sourceType] ?? 'Completed Task';

            $title       = $sourceModel->title ?? $sourceModel->name ?? null;
            $description = $customDescription ?? ($title ? "{$baseDescription}: {$title}" : $baseDescription);

            $user->increment('total_xp', $xpAmount);

            $user->expTransactions()->create([
                'amount'      => $xpAmount,
                'description' => $description,
                'source_id'   => $sourceId,
                'source_type' => $sourceType,
            ]);

            $levelUpdate = $this->updateUserLevel($user);

            DB::commit();

            return [
                'xp_added'   => $xpAmount,
                'old_xp'     => $oldXp,
                'new_xp'     => $user->total_xp,
                'leveled_up' => $levelUpdate['leveled_up'],
                'old_level'  => $levelUpdate['old_level'],
                'new_level'  => $levelUpdate['new_level'],
                'level_info' => $levelUpdate['level_info'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get leaderboard of top users by level and XP
     *
     * @param int $limit
     * @return Collection
     */
    public function getLeaderboard(int $limit = 10): Collection
    {
        return User::select('id', 'username', 'first_name', 'last_name', 'avatar', 'current_level', 'total_xp')
            ->where('role_id', '!=', 1)
            ->orderByDesc('current_level')
            ->orderByDesc('total_xp')
            ->limit($limit)
            ->get()
            ->map(function ($user) {
                $levelInfo = $this->getUserLevelInfo($user);
                return [
                    'user'       => [
                        'id'        => $user->id,
                        'username'  => $user->username,
                        'full_name' => trim($user->first_name . ' ' . $user->last_name),
                        'avatar'    => $user->avatar,
                    ],
                    'level_info' => $levelInfo,
                ];
            });
    }

    /**
     * Get user's rank position in leaderboard
     *
     * @param User $user
     * @return int
     */
    public function getUserRankData(User $user): array
    {
        $totalUsers = User::where('role_id', '!=', 1)->count();

        $rank = User::where('role_id', '!=', 1)
            ->where(function ($query) use ($user) {
                $query->where('current_level', '>', $user->current_level)
                    ->orWhere(function ($q) use ($user) {
                        $q->where('current_level', '=', $user->current_level)
                            ->where('total_xp', '>', $user->total_xp);
                    });
            })
            ->count() + 1;

        // Avoid division by zero
        $topPercentage = $totalUsers > 0
            ? round((($totalUsers - $rank + 1) / $totalUsers) * 100, 2)
            : 0;

        return [
            'rank'           => $rank,
            'total_users'    => $totalUsers,
            'top_percentage' => $topPercentage,
        ];
    }

    /**
     * Get level progression data for charts/graphs
     *
     * @param int $maxLevel
     * @return array
     */
    public function getLevelProgression(int $maxLevel = 50): array
    {
        $progression = [];

        for ($level = 1; $level <= $maxLevel; $level++) {
            $progression[] = [
                'level'             => $level,
                'total_xp_required' => $this->calculateXpForLevel($level),
                'xp_for_this_level' => $this->calculateXpForNextLevel($level - 1),
                'milestone'         => $this->getLevelMilestone($level),
            ];
        }

        return $progression;
    }

    /**
     * Clear level milestones cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get all users at a specific level
     *
     * @param int $level
     * @return Collection
     */
    public function getUsersAtLevel(int $level): Collection
    {
        return User::where('current_level', $level)
            ->where('role_id', '!=', 1)
            ->select('id', 'username', 'first_name', 'last_name', 'avatar', 'current_level', 'total_xp')
            ->get();
    }

    /**
     * Calculate how many more XP needed for a specific level
     *
     * @param User $user
     * @param int $targetLevel
     * @return int
     */
    public function calculateXpNeededForLevel(User $user, int $targetLevel): int
    {
        $currentXp = $user->total_xp ?? 0;
        $xpNeeded  = $this->calculateXpForLevel($targetLevel);

        return max(0, $xpNeeded - $currentXp);
    }
}

<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserStreak;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class UserActivityService
{
    /**
     * Log a user activity
     */
    public function logActivity(
        User $user, 
        string $activityType,
        $activityable = null,
    ): UserActivity {
        $activity = UserActivity::create([
            'user_id' => $user->id,
            'activity_type' => $activityType,
            'activity_date' => now()->toDateString(),
            'activityable_type' => $activityable ? get_class($activityable) : null,
            'activityable_id' => $activityable?->id
        ]);

        $this->updateUserStreak($user);

        return $activity;
    }


    protected function updateUserStreak(User $user): void
    {
        $streak = UserStreak::firstOrCreate(
            ['user_id' => $user->id],
            [
                'current_streak' => 0, 
                'longest_streak' => 0, 
                'last_activity_date' => null
            ]
        );

        $today = now()->startOfDay();
        $lastActivityDate = $streak->last_activity_date 
            ? Carbon::parse($streak->last_activity_date)->startOfDay() 
            : null;

        if ($lastActivityDate && $lastActivityDate->equalTo($today)) {
            return;
        }

        $yesterday = now()->subDay()->startOfDay();
        
        if ($lastActivityDate && $lastActivityDate->equalTo($yesterday)) {
            $streak->current_streak++;
        } else {
            $streak->current_streak = 1;
        }

        if ($streak->current_streak > $streak->longest_streak) {
            $streak->longest_streak = $streak->current_streak;
        }

        $streak->last_activity_date = now();
        $streak->save();
    }

    /**
     * Get current activity streak from UserStreak table
     */
    public function getCurrentStreak(User $user): int
    {
        $streak = UserStreak::where('user_id', $user->id)->first();

        if (!$streak || !$streak->last_activity_date) {
            return 0;
        }

        $lastActive = Carbon::parse($streak->last_activity_date);
        
        if ($lastActive->isToday() || $lastActive->isYesterday()) {
            return $streak->current_streak;
        }

        return 0;
    }

    /**
     * Get longest streak ever from UserStreak table
     */
    public function getLongestStreak(User $user): int
    {
        return UserStreak::where('user_id', $user->id)->value('longest_streak') ?? 0;
    }

    /**
     * Get activity heatmap data formatted for frontend
     * Returns array of objects with date, count, and level
     */
    public function getHeatmapData(User $user, int $days = 365): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        // Get activity counts grouped by date
        $activities = UserActivity::where('user_id', $user->id)
            ->whereBetween('activity_date', [
                $startDate->toDateString(), 
                $endDate->toDateString()
            ])
            ->select('activity_date', DB::raw('COUNT(*) as count'))
            ->groupBy('activity_date')
            ->get()
            ->keyBy('activity_date');

        $heatmapData = [];
        $currentDate = $startDate->copy();

        // Generate data for all days in range
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->toDateString();
            $count = $activities->get($dateStr)?->count ?? 0;

            $heatmapData[] = [
                'date' => $dateStr,
                'count' => $count,
                'level' => $this->getActivityLevel($count),
            ];

            $currentDate->addDay();
        }

        return $heatmapData;
    }

    /**
     * Get activity level (0-4) based on count
     * Matches typical GitHub contribution levels
     */
    protected function getActivityLevel(int $count): int
    {
        if ($count === 0) return 0;
        if ($count <= 3) return 1;
        if ($count <= 6) return 2;
        if ($count <= 9) return 3;
        return 4;
    }

    /**
     * Check consecutive days active
     */
    public function getConsecutiveDaysActive(User $user): int
    {
        return $this->getCurrentStreak($user);
    }

    /**
     * Get activity summary for a date range
     */
    public function getActivitySummary(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $activities = UserActivity::where('user_id', $user->id)
            ->whereBetween('activity_date', [
                $startDate->toDateString(),
                $endDate->toDateString()
            ])
            ->select(
                'activity_type',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('activity_type')
            ->get();

        $totalDays = UserActivity::where('user_id', $user->id)
            ->whereBetween('activity_date', [
                $startDate->toDateString(),
                $endDate->toDateString()
            ])
            ->distinct('activity_date')
            ->count('activity_date');

        return [
            'total_activities' => $activities->sum('count'),
            'active_days' => $totalDays,
            'by_type' => $activities->toArray(),
        ];
    }

    /**
     * Get weekly activity stats
     */
    public function getWeeklyStats(User $user): array
    {
        $startDate = now()->startOfWeek();
        $endDate = now()->endOfWeek();

        return $this->getActivitySummary($user, $startDate, $endDate);
    }

    /**
     * Get monthly activity stats
     */
    public function getMonthlyStats(User $user): array
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        return $this->getActivitySummary($user, $startDate, $endDate);
    }

    /**
     * Get yearly activity stats
     */
    public function getYearlyStats(User $user): array
    {
        $startDate = now()->startOfYear();
        $endDate = now()->endOfYear();

        return $this->getActivitySummary($user, $startDate, $endDate);
    }

    /**
     * Get total contributions for a year
     */
    public function getYearlyContributions(User $user, ?int $year = null): int
    {
        $year = $year ?? now()->year;
        
        return UserActivity::where('user_id', $user->id)
            ->whereYear('activity_date', $year)
            ->count();
    }

    /**
     * Check if user was active today
     */
    public function wasActiveToday(User $user): bool
    {
        return UserActivity::where('user_id', $user->id)
            ->where('activity_date', now()->toDateString())
            ->exists();
    }

    /**
     * Get most active day of week
     */
    public function getMostActiveDayOfWeek(User $user): ?string
    {
        $activities = UserActivity::where('user_id', $user->id)
            ->select(DB::raw('DAYOFWEEK(activity_date) as day_of_week'), DB::raw('COUNT(*) as count'))
            ->groupBy('day_of_week')
            ->orderByDesc('count')
            ->first();

        if (!$activities) {
            return null;
        }

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return $days[$activities->day_of_week - 1];
    }

    /**
     * Get activity trends (comparing periods)
     */
    public function getActivityTrends(User $user): array
    {
        $thisWeek = $this->getWeeklyStats($user);
        $lastWeek = $this->getActivitySummary(
            $user,
            now()->subWeek()->startOfWeek(),
            now()->subWeek()->endOfWeek()
        );

        $change = $thisWeek['total_activities'] - $lastWeek['total_activities'];
        $changePercentage = $lastWeek['total_activities'] > 0 
            ? round(($change / $lastWeek['total_activities']) * 100, 2)
            : 0;

        return [
            'this_week' => $thisWeek['total_activities'],
            'last_week' => $lastWeek['total_activities'],
            'change' => $change,
            'change_percentage' => $changePercentage,
            'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
        ];
    }

    /**
     * Get activity breakdown by type for a period
     */
    public function getActivityBreakdown(User $user, Carbon $startDate, Carbon $endDate): Collection
    {
        return UserActivity::where('user_id', $user->id)
            ->whereBetween('activity_date', [
                $startDate->toDateString(),
                $endDate->toDateString()
            ])
            ->select('activity_type', DB::raw('COUNT(*) as count'))
            ->groupBy('activity_type')
            ->orderByDesc('count')
            ->get();
    }

    /**
     * Get peak activity hours (if you're storing timestamps)
     */
    public function getPeakActivityHour(User $user): ?int
    {
        $activity = UserActivity::where('user_id', $user->id)
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        return $activity?->hour;
    }

    /**
     * Get overall activity statistics
     */
    public function getOverallStats(User $user): array
    {
        $totalActivities = UserActivity::where('user_id', $user->id)->count();
        $totalDays = UserActivity::where('user_id', $user->id)
            ->distinct('activity_date')
            ->count('activity_date');
        
        $firstActivity = UserActivity::where('user_id', $user->id)
            ->orderBy('activity_date', 'asc')
            ->first();

        return [
            'total_activities' => $totalActivities,
            'total_active_days' => $totalDays,
            'current_streak' => $this->getCurrentStreak($user),
            'longest_streak' => $this->getLongestStreak($user),
            'average_per_day' => $totalDays > 0 ? round($totalActivities / $totalDays, 2) : 0,
            'member_since' => $firstActivity?->activity_date,
            'most_active_day' => $this->getMostActiveDayOfWeek($user),
        ];
    }
}
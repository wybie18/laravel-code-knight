<?php

namespace App\Services;

use App\Models\User;
use App\Models\Challenge;
use App\Models\CodingChallenge;
use App\Models\CtfChallenge;
use App\Models\TypingChallenge;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    /**
     * Get leaderboard based on user levels (total XP)
     */
    public function getLevelLeaderboard($limit = 50)
    {
        return User::select([
            'id',
            'username',
            'first_name',
            'last_name',
            'avatar',
            'total_xp',
            'current_level'
        ])
        ->where('role_id', 2) // students
        ->orderBy('total_xp', 'desc')
        ->orderBy('current_level', 'desc')
        ->limit($limit)
        ->get()
        ->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user_id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'total_xp' => $user->total_xp,
                'level' => $user->current_level,
            ];
        });
    }

    /**
     * Get leaderboard for coding challenges
     */
    public function getCodingChallengeLeaderboard($limit = 50)
    {
        return User::select([
            'users.id',
            'users.username',
            'users.first_name',
            'users.last_name',
            'users.avatar',
            DB::raw('COUNT(DISTINCT challenges.id) as challenges_solved'),
            DB::raw('SUM(challenges.points) as total_points')
        ])
        ->join('challenge_submissions', 'users.id', '=', 'challenge_submissions.user_id')
        ->join('challenges', 'challenge_submissions.challenge_id', '=', 'challenges.id')
        ->where('users.role_id', 2) // students
        ->where('challenge_submissions.is_correct', true)
        ->where('challenges.challengeable_type', CodingChallenge::class)
        ->groupBy('users.id', 'users.username', 'users.first_name', 'users.last_name', 'users.avatar')
        ->orderBy('challenges_solved', 'desc')
        ->orderBy('total_points', 'desc')
        ->limit($limit)
        ->get()
        ->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user_id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'challenges_solved' => $user->challenges_solved,
                'total_points' => $user->total_points ?? 0,
            ];
        });
    }

    /**
     * Get leaderboard for CTF challenges
     */
    public function getCtfChallengeLeaderboard($limit = 50)
    {
        return User::select([
            'users.id',
            'users.username',
            'users.first_name',
            'users.last_name',
            'users.avatar',
            DB::raw('COUNT(DISTINCT challenges.id) as challenges_solved'),
            DB::raw('SUM(challenges.points) as total_points')
        ])
        ->join('challenge_submissions', 'users.id', '=', 'challenge_submissions.user_id')
        ->join('challenges', 'challenge_submissions.challenge_id', '=', 'challenges.id')
        ->where('users.role_id', 2) // students
        ->where('challenge_submissions.is_correct', true)
        ->where('challenges.challengeable_type', CtfChallenge::class)
        ->groupBy('users.id', 'users.username', 'users.first_name', 'users.last_name', 'users.avatar')
        ->orderBy('challenges_solved', 'desc')
        ->orderBy('total_points', 'desc')
        ->limit($limit)
        ->get()
        ->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user_id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'challenges_solved' => $user->challenges_solved,
                'total_points' => $user->total_points ?? 0,
            ];
        });
    }

    /**
     * Get leaderboard for typing challenges
     */
    public function getTypingChallengeLeaderboard($limit = 50)
    {
        return User::select([
            'users.id',
            'users.username',
            'users.first_name',
            'users.last_name',
            'users.avatar',
            DB::raw('COUNT(DISTINCT challenges.id) as challenges_solved'),
            DB::raw('SUM(challenges.points) as total_points')
        ])
        ->join('challenge_submissions', 'users.id', '=', 'challenge_submissions.user_id')
        ->join('challenges', 'challenge_submissions.challenge_id', '=', 'challenges.id')
        ->where('users.role_id', 2) // students
        ->where('challenge_submissions.is_correct', true)
        ->where('challenges.challengeable_type', TypingChallenge::class)
        ->groupBy('users.id', 'users.username', 'users.first_name', 'users.last_name', 'users.avatar')
        ->orderBy('challenges_solved', 'desc')
        ->orderBy('total_points', 'desc')
        ->limit($limit)
        ->get()
        ->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user_id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'challenges_solved' => $user->challenges_solved,
                'total_points' => $user->total_points ?? 0,
            ];
        });
    }

    /**
     * Get overall challenge leaderboard (all challenge types combined)
     */
    public function getOverallChallengeLeaderboard($limit = 50)
    {
        return User::select([
            'users.id',
            'users.username',
            'users.first_name',
            'users.last_name',
            'users.avatar',
            DB::raw('COUNT(DISTINCT challenges.id) as challenges_solved'),
            DB::raw('SUM(challenges.points) as total_points')
        ])
        ->join('challenge_submissions', 'users.id', '=', 'challenge_submissions.user_id')
        ->join('challenges', 'challenge_submissions.challenge_id', '=', 'challenges.id')
        ->where('users.role_id', 2) // students
        ->where('challenge_submissions.is_correct', true)
        ->groupBy('users.id', 'users.username', 'users.first_name', 'users.last_name', 'users.avatar')
        ->orderBy('challenges_solved', 'desc')
        ->orderBy('total_points', 'desc')
        ->limit($limit)
        ->get()
        ->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user_id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'challenges_solved' => $user->challenges_solved,
                'total_points' => $user->total_points ?? 0,
            ];
        });
    }

    /**
     * Get leaderboard based on achievements earned
     */
    public function getAchievementLeaderboard($limit = 50)
    {
        return User::select([
            'users.id',
            'users.username',
            'users.first_name',
            'users.last_name',
            'users.avatar',
            DB::raw('COUNT(user_achievements.id) as achievements_earned')
        ])
        ->join('user_achievements', 'users.id', '=', 'user_achievements.user_id')
        ->where('users.role_id', 2) // Only students
        ->groupBy('users.id', 'users.username', 'users.first_name', 'users.last_name', 'users.avatar')
        ->orderBy('achievements_earned', 'desc')
        ->limit($limit)
        ->get()
        ->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user_id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'achievements_earned' => $user->achievements_earned,
            ];
        });
    }

    /**
     * Get leaderboard based on current streak
     */
    public function getStreakLeaderboard($limit = 50)
    {
        return User::select([
            'users.id',
            'users.username',
            'users.first_name',
            'users.last_name',
            'users.avatar',
            'user_streaks.current_streak',
            'user_streaks.longest_streak'
        ])
        ->join('user_streaks', 'users.id', '=', 'user_streaks.user_id')
        ->where('users.role_id', 2) // Only students
        ->orderBy('user_streaks.current_streak', 'desc')
        ->orderBy('user_streaks.longest_streak', 'desc')
        ->limit($limit)
        ->get()
        ->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user_id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'current_streak' => $user->current_streak,
                'longest_streak' => $user->longest_streak,
            ];
        });
    }

    /**
     * Get leaderboard based on course completions
     */
    public function getCourseCompletionLeaderboard($limit = 50)
    {
        return User::select([
            'users.id',
            'users.username',
            'users.first_name',
            'users.last_name',
            'users.avatar',
            DB::raw('COUNT(user_course_progress.id) as courses_completed')
        ])
        ->join('user_course_progress', 'users.id', '=', 'user_course_progress.user_id')
        ->where('users.role_id', 2) // Only students
        ->whereNotNull('user_course_progress.completed_at')
        ->groupBy('users.id', 'users.username', 'users.first_name', 'users.last_name', 'users.avatar')
        ->orderBy('courses_completed', 'desc')
        ->limit($limit)
        ->get()
        ->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user_id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'courses_completed' => $user->courses_completed,
            ];
        });
    }

    /**
     * Get user's rank and stats for a specific leaderboard type
     */
    public function getUserRank($userId, $leaderboardType)
    {
        $leaderboard = $this->getLeaderboard($leaderboardType);
        
        $userRank = $leaderboard->firstWhere('user_id', $userId);
        
        return $userRank ?? null;
    }

    /**
     * Helper method to get leaderboard by type
     */
    public function getLeaderboard($type, $limit = 50)
    {
        return match($type) {
            'levels' => $this->getLevelLeaderboard($limit),
            'coding' => $this->getCodingChallengeLeaderboard($limit),
            'ctf' => $this->getCtfChallengeLeaderboard($limit),
            'typing' => $this->getTypingChallengeLeaderboard($limit),
            'overall' => $this->getOverallChallengeLeaderboard($limit),
            'achievements' => $this->getAchievementLeaderboard($limit),
            'streaks' => $this->getStreakLeaderboard($limit),
            'courses' => $this->getCourseCompletionLeaderboard($limit),
            default => collect([]),
        };
    }
}

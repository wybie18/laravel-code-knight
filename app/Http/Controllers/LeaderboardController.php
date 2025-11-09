<?php

namespace App\Http\Controllers;

use App\Services\LeaderboardService;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    protected $leaderboardService;

    public function __construct(LeaderboardService $leaderboardService)
    {
        $this->leaderboardService = $leaderboardService;
    }

    /**
     * Get leaderboard based on type
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:levels,coding,ctf,typing,overall,achievements,streaks,courses',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $type = $request->input('type');
        $limit = $request->input('limit', 50);

        $leaderboard = $this->leaderboardService->getLeaderboard($type, $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $type,
                'leaderboard' => $leaderboard
            ]
        ]);
    }

    /**
     * Get level leaderboard
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function levels(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $limit = $request->input('limit', 50);
        $leaderboard = $this->leaderboardService->getLevelLeaderboard($limit);

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'levels',
                'leaderboard' => $leaderboard
            ]
        ]);
    }

    /**
     * Get coding challenge leaderboard
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function coding(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $limit = $request->input('limit', 50);
        $leaderboard = $this->leaderboardService->getCodingChallengeLeaderboard($limit);

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'coding',
                'leaderboard' => $leaderboard
            ]
        ]);
    }

    /**
     * Get CTF challenge leaderboard
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ctf(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $limit = $request->input('limit', 50);
        $leaderboard = $this->leaderboardService->getCtfChallengeLeaderboard($limit);

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'ctf',
                'leaderboard' => $leaderboard
            ]
        ]);
    }

    /**
     * Get typing challenge leaderboard
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function typing(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $limit = $request->input('limit', 50);
        $leaderboard = $this->leaderboardService->getTypingChallengeLeaderboard($limit);

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'typing',
                'leaderboard' => $leaderboard
            ]
        ]);
    }

    /**
     * Get overall challenge leaderboard (all types combined)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function overall(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $limit = $request->input('limit', 50);
        $leaderboard = $this->leaderboardService->getOverallChallengeLeaderboard($limit);

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'overall',
                'leaderboard' => $leaderboard
            ]
        ]);
    }

    /**
     * Get achievement leaderboard
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function achievements(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $limit = $request->input('limit', 50);
        $leaderboard = $this->leaderboardService->getAchievementLeaderboard($limit);

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'achievements',
                'leaderboard' => $leaderboard
            ]
        ]);
    }

    /**
     * Get streak leaderboard
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function streaks(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $limit = $request->input('limit', 50);
        $leaderboard = $this->leaderboardService->getStreakLeaderboard($limit);

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'streaks',
                'leaderboard' => $leaderboard
            ]
        ]);
    }

    /**
     * Get course completion leaderboard
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function courses(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $limit = $request->input('limit', 50);
        $leaderboard = $this->leaderboardService->getCourseCompletionLeaderboard($limit);

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'courses',
                'leaderboard' => $leaderboard
            ]
        ]);
    }

    /**
     * Get user's rank in a specific leaderboard
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myRank(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:levels,coding,ctf,typing,overall,achievements,streaks,courses',
        ]);

        $user = $request->user();
        $type = $request->input('type');

        $userRank = $this->leaderboardService->getUserRank($user->id, $type);

        if (!$userRank) {
            return response()->json([
                'success' => true,
                'data' => [
                    'type' => $type,
                    'rank' => null,
                    'message' => 'You are not ranked yet in this leaderboard.'
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $type,
                'rank' => $userRank
            ]
        ]);
    }

    /**
     * Get all leaderboards at once (summary)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:20'
        ]);

        $limit = $request->input('limit', 10);

        return response()->json([
            'success' => true,
            'data' => [
                'levels' => $this->leaderboardService->getLevelLeaderboard($limit),
                'coding' => $this->leaderboardService->getCodingChallengeLeaderboard($limit),
                'ctf' => $this->leaderboardService->getCtfChallengeLeaderboard($limit),
                'typing' => $this->leaderboardService->getTypingChallengeLeaderboard($limit),
                'overall' => $this->leaderboardService->getOverallChallengeLeaderboard($limit),
                'achievements' => $this->leaderboardService->getAchievementLeaderboard($limit),
                'streaks' => $this->leaderboardService->getStreakLeaderboard($limit),
                'courses' => $this->leaderboardService->getCourseCompletionLeaderboard($limit),
            ]
        ]);
    }
}

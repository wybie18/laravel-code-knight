<?php

namespace App\Http\Controllers\Challenge;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChallengeResource;
use App\Models\Challenge;
use App\Models\CodingChallenge;
use App\Models\CtfChallenge;
use App\Models\TypingChallenge;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    public function mySolvedChallenges(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $limit = $request->input('limit', 10);
        
        $user = $request->user();

        $solvedChallenges = Challenge::whereHas('submissions', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->where('is_correct', true);
        })
        ->with(['difficulty', 'challengeable'])
        ->withCount(['submissions as is_solved' => function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->where('is_correct', true);
        }])
        ->limit($limit)
        ->latest()
        ->get()
        ->map(function ($challenge) {
            $challenge->is_solved = $challenge->is_solved > 0;
            return $challenge;
        });

        return ChallengeResource::collection($solvedChallenges)->additional([
            'success' => true,
        ]);
    }

    public function getChallengesProgress(Request $request)
    {
        $user = $request->user();

        // Get total count of challenges by type
        $totalCodingChallenges = Challenge::where('challengeable_type', CodingChallenge::class)->count();
        $totalCtfChallenges = Challenge::where('challengeable_type', CtfChallenge::class)->count();
        $totalTypingChallenges = Challenge::where('challengeable_type', TypingChallenge::class)->count();

        // Get completed challenges by type
        $completedCodingChallenges = Challenge::where('challengeable_type', CodingChallenge::class)
            ->whereHas('submissions', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('is_correct', true);
            })
            ->count();

        $completedCtfChallenges = Challenge::where('challengeable_type', CtfChallenge::class)
            ->whereHas('submissions', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('is_correct', true);
            })
            ->count();

        $completedTypingChallenges = Challenge::where('challengeable_type', TypingChallenge::class)
            ->whereHas('submissions', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('is_correct', true);
            })
            ->count();

        // Calculate overall stats
        $totalChallenges = $totalCodingChallenges + $totalCtfChallenges + $totalTypingChallenges;
        $completedChallenges = $completedCodingChallenges + $completedCtfChallenges + $completedTypingChallenges;

        return response()->json([
            'success' => true,
            'data' => [
                'overall' => [
                    'total' => $totalChallenges,
                    'completed' => $completedChallenges,
                ],
                'coding' => [
                    'total' => $totalCodingChallenges,
                    'completed' => $completedCodingChallenges,
                ],
                'ctf' => [
                    'total' => $totalCtfChallenges,
                    'completed' => $completedCtfChallenges,
                ],
                'typing' => [
                    'total' => $totalTypingChallenges,
                    'completed' => $completedTypingChallenges,
                ],
            ],
        ]);
    }
}

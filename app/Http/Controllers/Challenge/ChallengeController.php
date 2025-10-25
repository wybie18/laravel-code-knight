<?php

namespace App\Http\Controllers\Challenge;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChallengeResource;
use App\Models\Challenge;
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
}

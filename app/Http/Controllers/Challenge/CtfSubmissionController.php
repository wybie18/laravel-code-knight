<?php
namespace App\Http\Controllers\Challenge;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\CtfChallenge;
use Illuminate\Http\Request;

class CtfSubmissionController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $request->validate([
            'flag'         => 'required|string|max:255',
        ]);
        $user      = $request->user();
        $challenge = Challenge::where('slug', $slug)
            ->where('challengeable_type', CtfChallenge::class)->firstOrFail();
        $ctfChallenge = $challenge->challengeable;
        $isCorrect    = $ctfChallenge->flag === $request->flag;

        $alreadySolved = ChallengeSubmission::where('challenge_id', $challenge->id)
            ->where('is_correct', true)
            ->exists();

        if ($alreadySolved) {
            return response()->json(['success' => false, 'message' => 'Challenge already solved.'], 400);
        }

        ChallengeSubmission::create([
            'user_id'            => $user->id,
            'challenge_id'       => $challenge->id,
            'submission_content' => $request->flag,
            'is_correct'         => $isCorrect,
            'results'            => ['submitted_at' => now()],
        ]);

        if ($isCorrect) {
            $user->expTransactions()->create([
                'amount'      => $challenge->points,
                'description' => "Solved CTF Challenge: {$challenge->title}",
                'source_type' => Challenge::class,
                'source_id'   => $challenge->id,
            ]);
            $user->increment('total_xp', $challenge->points);

            return response()->json(['success' => true, 'message' => 'Correct flag! Challenge solved.'], 200);
        }

        return response()->json(['success' => false, 'message' => 'Incorrect flag. Try again!'], 200);
    }
}

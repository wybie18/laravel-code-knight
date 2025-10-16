<?php
namespace App\Http\Controllers\Challenge;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\CtfChallenge;
use App\Services\LevelService;
use App\Services\UserActivityService;
use Illuminate\Http\Request;

class CtfSubmissionController extends Controller
{
    private $levelService;
    private $userActivityService;

    public function __construct(LevelService $levelService, UserActivityService $userActivityService)
    {
        $this->levelService = $levelService;
        $this->userActivityService = $userActivityService;
    }

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
        ]);

        if ($isCorrect) {
            $description = "Solved CTF Challenge: {$challenge->title}";
            $this->levelService->addXp($user, $challenge->points, $description, $challenge);
            return response()->json(['success' => true, 'message' => 'Correct flag! Challenge solved.'], 200);
        }

        $this->userActivityService->logActivity($user, "ctf_challenge_submission", $challenge);

        return response()->json(['success' => false, 'message' => 'Incorrect flag. Try again!'], 200);
    }
}

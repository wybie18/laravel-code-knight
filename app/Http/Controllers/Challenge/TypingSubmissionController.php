<?php
namespace App\Http\Controllers\Challenge;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\TypingChallenge;
use App\Services\LevelService;
use App\Services\UserActivityService;
use Illuminate\Http\Request;

class TypingSubmissionController extends Controller
{
    private $levelService;
    private $userActivityService;

    public function __construct(LevelService $levelService, UserActivityService $userActivityService)
    {
        $this->levelService = $levelService;
        $this->userActivityService = $userActivityService;
    }

    public function getSubmissionHistory(Request $request, string $slug)
    {
        if (! $request->user()->tokenCan('admin:*') && ! $request->user()->tokenCan('challenge:view')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $challenge = Challenge::where('slug', $slug)
            ->where('challengeable_type', TypingChallenge::class)->firstOrFail();

        $submissions = ChallengeSubmission::where('challenge_id', $challenge->id)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => $submissions,
        ], 200);
    }

    public function store(Request $request, string $slug)
    {
        $request->validate([
            'user_input'          => 'required|string',
            'wpm'                 => 'required|integer|min:0',
            'accuracy'            => 'required|numeric|between:0,100',
            'errors'              => 'required|integer|min:0',
            'time_taken'          => 'required|numeric|min:0',
            'total_chars_typed'   => 'required|integer|min:0',
            'correct_chars_typed' => 'required|integer|min:0',
            'is_completed'        => 'required|boolean',
        ]);

        $user      = $request->user();
        $challenge = Challenge::where('slug', $slug)
            ->where('challengeable_type', TypingChallenge::class)->firstOrFail();
        $typingChallenge = $challenge->challengeable;

        $isCorrect = $request->wpm >= $typingChallenge->target_wpm
        && $request->accuracy >= $typingChallenge->target_accuracy
        && $request->is_completed;

        $alreadySolved = ChallengeSubmission::where('user_id', $user->id)
            ->where('challenge_id', $challenge->id)
            ->where('is_correct', true)
            ->exists();

        if ($alreadySolved) {
            return response()->json(['success' => false, 'message' => 'Challenge already completed.'], 400);
        }

        ChallengeSubmission::create([
            'user_id'            => $user->id,
            'challenge_id'       => $challenge->id,
            'submission_content' => $request->user_input,
            'is_correct'         => $isCorrect,
            'results'            => [
                'wpm'                 => $request->wpm,
                'accuracy'            => $request->accuracy,
                'errors'              => $request->errors,
                'time_taken'          => $request->time_taken,
                'total_chars_typed'   => $request->total_chars_typed,
                'correct_chars_typed' => $request->correct_chars_typed,
                'is_completed'        => $request->is_completed,
            ],
        ]);

        if ($isCorrect) {
            $description = "Completed Typing Challenge: {$challenge->title}";
            $this->levelService->addXp($user, $challenge->points, $description, $challenge);
            return response()->json(['success' => true, 'message' => 'Challenge completed! All targets achieved.'], 200);
        }

        $this->userActivityService->logActivity($user, "typing_test_challenge_submission", $challenge);

        return response()->json(['success' => false, 'message' => 'Keep practicing! Try to meet the WPM and accuracy targets.'], 200);
    }
}

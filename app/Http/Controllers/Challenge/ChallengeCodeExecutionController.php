<?php
namespace App\Http\Controllers\Challenge;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\CodingChallenge;
use App\Models\ProgrammingLanguage;
use App\Services\Judge0TestRunner;
use Illuminate\Http\Request;

class ChallengeCodeExecutionController extends Controller
{
    private $testRunner;

    public function __construct(Judge0TestRunner $testRunner)
    {
        $this->testRunner = $testRunner;
    }

    public function executeCode(Request $request, string $slug)
    {
        $request->validate([
            'language_id' => 'required|integer|exists:programming_languages,id',
            'user_code'   => 'required|string',
        ]);

        try {
            $challenge = Challenge::where('slug', $slug)
                ->where('challengeable_type', CodingChallenge::class)->firstOrFail();

            if (! $challenge || ! $challenge->challengeable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Challenge not found',
                ], 404);
            }

            $language = ProgrammingLanguage::findOrFail($request->language_id);

            $codingChallenge = $challenge->challengeable;

            $testCases = json_decode($codingChallenge->test_cases, true);

            if (empty($testCases)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No test cases found for this challenge',
                ], 400);
            }

            $results = $this->testRunner->runTestCases($codingChallenge, $language, $request->user_code, 3);

            return response()->json([
                'success' => true,
                'data'    => $results,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Code execution failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function submitCode(Request $request, string $slug)
    {
        $request->validate([
            'language_id' => 'required|integer|exists:programming_languages,id',
            'user_code'   => 'required|string',
        ]);

        try {
            $challenge = Challenge::where('slug', $slug)
                ->where('challengeable_type', CodingChallenge::class)->firstOrFail();

            if (! $challenge || ! $challenge->challengeable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Challenge not found',
                ], 404);
            }

            $language = ProgrammingLanguage::findOrFail($request->language_id);

            $codingChallenge = $challenge->challengeable;

            $testCases = json_decode($codingChallenge->test_cases, true);

            if (empty($testCases)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No test cases found for this challenge',
                ], 400);
            }

            $results = $this->testRunner->runTestCases($codingChallenge, $language, $request->user_code);

            ChallengeSubmission::create([
                'user_id'            => $request->user()->id,
                'challenge_id'       => $challenge->id,
                'submission_content' => $request->user_code,
                'is_correct'         => collect($results)->every(fn($result) => $result['status'] === 'passed'),
                'results'            => $results,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $results,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Code execution failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}

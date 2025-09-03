<?php
namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\CodingChallenge;
use App\Models\ProgrammingLanguage;

class CodeExecutionService
{
    private $testRunner;

    public function __construct(Judge0TestRunner $testRunner)
    {
        $this->testRunner = $testRunner;
    }

    public function executeCode(string $slug, int $languageId, string $userCode, ?int $userId = null): array
    {
        $challenge       = $this->getChallengeBySlug($slug);
        $language        = ProgrammingLanguage::findOrFail($languageId);
        $codingChallenge = $challenge->challengeable;

        $this->validateTestCases($codingChallenge);

        $results = $this->testRunner->runTestCases($codingChallenge, $language, $userCode, 3);

        return [
            'challenge' => $challenge,
            'results'   => $results,
        ];
    }

    public function submitCode(string $slug, int $languageId, string $userCode, int $userId): array
    {
        $challenge       = $this->getChallengeBySlug($slug);
        $language        = ProgrammingLanguage::findOrFail($languageId);
        $codingChallenge = $challenge->challengeable;

        $this->validateTestCases($codingChallenge);

        $results = $this->testRunner->runTestCases($codingChallenge, $language, $userCode);

        $this->saveSubmission($challenge, $userId, $userCode, $results);

        return [
            'challenge' => $challenge,
            'results'   => $results,
        ];
    }

    private function getChallengeBySlug(string $slug): Challenge
    {
        $challenge = Challenge::where('slug', $slug)
            ->where('challengeable_type', CodingChallenge::class)
            ->firstOrFail();

        if (! $challenge->challengeable) {
            throw new \Exception('Challenge not found');
        }

        return $challenge;
    }

    private function validateTestCases(CodingChallenge $codingChallenge): void
    {
        $testCases = json_decode($codingChallenge->test_cases, true);

        if (empty($testCases)) {
            throw new \Exception('No test cases found for this challenge');
        }
    }

    private function saveSubmission(Challenge $challenge, int $userId, string $userCode, array $results): void
    {
        ChallengeSubmission::create([
            'user_id'            => $userId,
            'challenge_id'       => $challenge->id,
            'submission_content' => $userCode,
            'is_correct'         => collect($results)->every(fn($result) => $result['status'] === 'passed'),
            'results'            => $results,
        ]);
    }
}

// Updated Controller
namespace App\Http\Controllers\Challenge;

use App\Http\Controllers\Controller;
use App\Services\CodeExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChallengeCodeExecutionController extends Controller
{
    private $codeExecutionService;

    public function __construct(CodeExecutionService $codeExecutionService)
    {
        $this->codeExecutionService = $codeExecutionService;
    }

    public function executeCode(Request $request, string $slug): JsonResponse
    {
        $validatedData = $request->validate([
            'language_id' => 'required|integer|exists:programming_languages,id',
            'user_code'   => 'required|string',
        ]);

        try {
            $result = $this->codeExecutionService->executeCode(
                $slug,
                $validatedData['language_id'],
                $validatedData['user_code']
            );

            return response()->json([
                'success' => true,
                'data'    => $result['results'],
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    public function submitCode(Request $request, string $slug): JsonResponse
    {
        $validatedData = $request->validate([
            'language_id' => 'required|integer|exists:programming_languages,id',
            'user_code'   => 'required|string',
        ]);

        try {
            $result = $this->codeExecutionService->submitCode(
                $slug,
                $validatedData['language_id'],
                $validatedData['user_code'],
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'data'    => $result['results'],
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    private function handleError(\Exception $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Code execution failed',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

<?php
namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\CodingChallenge;
use App\Models\ProgrammingLanguage;
use App\Models\User;

class ChallengeCodeExecutionService
{
    private $testRunner;
    private $levelService;

    public function __construct(Judge0TestRunner $testRunner, LevelService $levelService)
    {
        $this->testRunner = $testRunner;
        $this->levelService = $levelService;
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
        $user            = User::findOrFail($userId);
        $language        = ProgrammingLanguage::findOrFail($languageId);
        $codingChallenge = $challenge->challengeable;

        $this->validateTestCases($codingChallenge);

        $alreadySolved = $this->checkIfAlreadySolved($challenge->id, $userId);

        $results = $this->testRunner->runTestCases($codingChallenge, $language, $userCode);

        $allTestsPassed = $results['passed'];

        $this->saveSubmission($challenge, $userId, $userCode, $results);

        if ($allTestsPassed && ! $alreadySolved) {
            $description = "Solved Coding Challenge: {$challenge->title}";
            $this->levelService->addXp($user, $challenge->points, $description, $challenge);
        }

        $executionTimes = [];
        $memoryUsages   = [];

        foreach ($results['results'] as $result) {
            if (isset($result['execution_time']) && $result['execution_time'] !== null) {
                $executionTimes[] = (float) $result['execution_time'];
            }
            if (isset($result['memory_usage']) && $result['memory_usage'] !== null) {
                $memoryUsages[] = (int) $result['memory_usage'];
            }
        }

        $totalExecutionTime = array_sum($executionTimes);
        $avgExecutionTime   = count($executionTimes) > 0 ? $totalExecutionTime / count($executionTimes) : 0;

        $totalMemoryUsage = array_sum($memoryUsages);
        $avgMemoryUsage   = count($memoryUsages) > 0 ? $totalMemoryUsage / count($memoryUsages) : 0;

        return [
            'passed'               => $allTestsPassed,
            'total_cases'          => $results['total_cases'],
            'passed_cases'         => $results['passed_cases'],
            'total_execution_time' => $totalExecutionTime,
            'avg_execution_time'   => $avgExecutionTime,
            'total_memory_usage'   => $totalMemoryUsage,
            'avg_memory_usage'     => $avgMemoryUsage,
            'source_code'          => $userCode,
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
        $testCases = $codingChallenge->test_cases;

        if (empty($testCases)) {
            throw new \Exception('No test cases found for this challenge');
        }
    }

    private function checkIfAlreadySolved(int $challengeId, int $userId): bool
    {
        return ChallengeSubmission::where('challenge_id', $challengeId)
            ->where('user_id', $userId)
            ->where('is_correct', true)
            ->exists();
    }

    private function saveSubmission(Challenge $challenge, int $userId, string $userCode, array $results): ChallengeSubmission
    {
        return ChallengeSubmission::create([
            'user_id'            => $userId,
            'challenge_id'       => $challenge->id,
            'submission_content' => $userCode,
            'is_correct'         => $results['passed'],
            'results'            => $results,
        ]);
    }

    /**
     * Get user's submission history for a challenge
     */
    public function getUserSubmissionHistory(string $slug, int $userId): array
    {
        $challenge = $this->getChallengeBySlug($slug);

        $submissions = ChallengeSubmission::where('challenge_id', $challenge->id)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'challenge'         => $challenge,
            'submissions'       => $submissions,
            'total_submissions' => $submissions->count(),
            'solved'            => $submissions->where('is_correct', true)->isNotEmpty(),
        ];
    }
}

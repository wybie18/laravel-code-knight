<?php
namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\CodingChallenge;
use App\Models\ProgrammingLanguage;

class ChallengeCodeExecutionService
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

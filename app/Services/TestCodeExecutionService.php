<?php
namespace App\Services;

use App\Models\CodingChallenge;
use App\Models\ProgrammingLanguage;
use App\Models\TestAttempt;
use App\Models\TestItem;
use App\Models\TestItemSubmission;
use Illuminate\Support\Facades\Auth;

class TestCodeExecutionService
{
    private $testRunner;

    public function __construct(Judge0TestRunner $testRunner)
    {
        $this->testRunner = $testRunner;
    }

    public function executeCode(int $testItemId, int $languageId, string $userCode): array
    {
        $testItem = TestItem::with('itemable')->findOrFail($testItemId);
        
        if (!$testItem->itemable instanceof CodingChallenge) {
            throw new \Exception('Test item is not a coding challenge.');
        }

        $codingChallenge = $testItem->itemable;
        $language = $this->findLanguage($languageId);

        $this->validateTestCases($codingChallenge);

        $results = $this->testRunner->runTestCases($codingChallenge, $language, $userCode, 3);

        return [
            'test_item' => $testItem,
            'results'   => $results,
        ];
    }

    public function submitCode(int $attemptId, int $testItemId, int $languageId, string $userCode): array
    {
        $attempt = TestAttempt::findOrFail($attemptId);
        $testItem = TestItem::with('itemable')->findOrFail($testItemId);
        
        if ($attempt->student_id !== Auth::id()) {
             throw new \Exception('Unauthorized.');
        }

        if (!$testItem->itemable instanceof CodingChallenge) {
            throw new \Exception('Test item is not a coding challenge.');
        }

        $codingChallenge = $testItem->itemable;
        $language = $this->findLanguage($languageId);

        $this->validateTestCases($codingChallenge);

        // Run all test cases
        $results = $this->testRunner->runTestCases($codingChallenge, $language, $userCode);
        $allTestsPassed = $results['passed'];

        // Save submission
        $submission = $this->saveSubmission($attempt, $testItem, $userCode, $results, $allTestsPassed);

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
            'submission_id'        => $submission->id,
            'results'              => $results['results'],
        ];
    }

    public function runAllTests(CodingChallenge $codingChallenge, int $languageId, string $userCode): array
    {
        $language = $this->findLanguage($languageId);

        $this->validateTestCases($codingChallenge);
        return $this->testRunner->runTestCases($codingChallenge, $language, $userCode);
    }

    private function findLanguage(int $languageId): ProgrammingLanguage
    {
        $language = ProgrammingLanguage::where('language_id', $languageId)->first();

        if (!$language) {
            $language = ProgrammingLanguage::find($languageId);
        }

        if (!$language) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Programming Language with ID $languageId not found.");
        }

        return $language;
    }

    private function validateTestCases(CodingChallenge $codingChallenge): void
    {
        $testCases = $codingChallenge->test_cases;

        if (empty($testCases)) {
            throw new \Exception('No test cases found for this challenge');
        }
    }

    private function saveSubmission(TestAttempt $attempt, TestItem $testItem, string $userCode, array $results, bool $passed): TestItemSubmission
    {
        $submission = TestItemSubmission::firstOrNew([
            'test_attempt_id' => $attempt->id,
            'test_item_id'    => $testItem->id,
        ]);

        $submission->answer = $userCode;
        $submission->is_correct = $passed;
        $submission->score = $passed ? $testItem->points : 0;
        
        $submission->save();

        return $submission;
    }
}
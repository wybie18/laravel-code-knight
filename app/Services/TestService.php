<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\CodingChallenge;
use App\Models\CtfChallenge;
use App\Models\EssayQuestion;
use App\Models\QuizQuestion;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestItem;
use App\Models\TestItemSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TestService
{
    private TestCodeExecutionService $testCodeExecutionService;

    public function __construct(TestCodeExecutionService $testCodeExecutionService)
    {
        $this->testCodeExecutionService = $testCodeExecutionService;
    }

    /**
     * Check if user can access the test
     */
    public function canUserAccessTest(User $user, Test $test): bool
    {
        // Teachers can access their own tests
        if ($test->teacher_id === $user->id) {
            return true;
        }

        // Admins can access all tests
        if ($user->tokenCan('admin:*')) {
            return true;
        }

        // Students must be assigned to the test
        if (!$test->students()->where('test_students.student_id', $user->id)->exists()) {
            return false;
        }

        // If test is part of a course, student must be enrolled
        if ($test->course_id) {
            $isEnrolled = $user->courseEnrollments()
                ->where('course_id', $test->course_id)
                ->exists();

            if (!$isEnrolled) {
                return false;
            }
        }

        return true;
    }

    /**
     * Start a new test attempt
     */
    public function startAttempt(User $user, Test $test): TestAttempt
    {
        if (!$test->canStartAttempt($user)) {
            throw new \Exception('Cannot start new attempt. Check test status and attempt limits.');
        }

        $attemptNumber = $test->attempts()
            ->where('student_id', $user->id)
            ->count() + 1;

        return TestAttempt::create([
            'test_id' => $test->id,
            'student_id' => $user->id,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
            'status' => 'in_progress',
        ]);
    }

    /**
     * Submit answer for a test item
     */
    public function submitItemAnswer(
        TestAttempt $attempt,
        TestItem $testItem,
        $answer
    ): TestItemSubmission {
        // Check if submission already exists
        $submission = TestItemSubmission::firstOrNew([
            'test_attempt_id' => $attempt->id,
            'test_item_id' => $testItem->id,
        ]);

        $submission->answer = is_array($answer) ? json_encode($answer) : $answer;

        // Auto-grade if possible
        $gradingResult = $this->autoGradeItem($testItem, $answer);
        
        if ($gradingResult) {
            $submission->score = $gradingResult['score'];
            $submission->is_correct = $gradingResult['is_correct'];
        }

        $submission->save();

        return $submission;
    }

    /**
     * Auto-grade a test item if possible
     */
    private function autoGradeItem(TestItem $testItem, $answer): ?array
    {
        $itemable = $testItem->itemable;

        // Auto-grade quiz questions
        if ($itemable instanceof QuizQuestion) {
            $isCorrect = $this->gradeQuizQuestion($itemable, $answer);
            return [
                'score' => $isCorrect ? $testItem->points : 0,
                'is_correct' => $isCorrect,
            ];
        }
        Log::info('Auto-grading test item', [
            'test_item_id' => $testItem->id,
            'itemable_type' => get_class($itemable),
        ]);
        if ($itemable instanceof CodingChallenge) {
            Log::info('Coding challenge answer received', [
                'test_item_id' => $testItem->id,
                'raw_answer' => $answer,
            ]);
            
            $answerData = is_string($answer) ? json_decode($answer, true) : $answer;

            // If json_decode failed (it returns null for invalid JSON), treat raw string as code
            if ($answerData === null && is_string($answer)) {
                $answerData = ['code' => $answer];
            }

            Log::info('Coding challenge answer data', [
                'test_item_id' => $testItem->id,
                'answer_data' => $answerData,
            ]);

            if (is_array($answerData) && !empty($answerData['code'])) {
                // If language_id is missing, try to find a default one from the challenge
                if (empty($answerData['language_id'])) {
                    $defaultLang = $itemable->programmingLanguages()->first();
                    if ($defaultLang) {
                        $answerData['language_id'] = $defaultLang->language_id;
                        Log::info('Using default language for grading', ['language_id' => $defaultLang->language_id]);
                    }
                }

                if (!empty($answerData['language_id'])) {
                    try {
                        $results = $this->testCodeExecutionService->runAllTests(
                            $itemable,
                            $answerData['language_id'],
                            $answerData['code']
                        );

                        Log::info('Auto-graded coding challenge', [
                            'test_item_id' => $testItem->id,
                            'results' => $results,
                        ]);
                        return [
                            'score' => $results['passed'] ? $testItem->points : 0,
                            'is_correct' => $results['passed'],
                        ];
                    } catch (\Exception $e) {
                        Log::error('Auto-grading exception', ['error' => $e->getMessage()]);
                        return null;
                    }
                }
            }
            return null;
        }

        // essay questions require manual grading
        return null;
    }

    /**
     * Grade a quiz question
     */
    private function gradeQuizQuestion(QuizQuestion $question, $answer): bool
    {
        $correctAnswer = $question->correct_answer;

        // Handle different question types
        switch ($question->type) {
            case 'multiple_choice':
            case 'fill_blank':
            case 'boolean':
                return strtolower(trim($answer)) === strtolower(trim($correctAnswer));
            default:
                return false;
        }
    }

    /**
     * Submit the entire test
     */
    public function submitTest(TestAttempt $attempt): array
    {
        if ($attempt->status !== 'in_progress') {
            throw new \Exception('Test attempt is not in progress.');
        }

        $attempt->submitted_at = now();
        $attempt->time_spent_minutes = $attempt->calculateTimeSpent();
        $attempt->status = 'submitted';

        // Calculate total score from auto-graded items
        $totalScore = $attempt->submissions()
            ->whereNotNull('score')
            ->sum('score');

        $attempt->total_score = $totalScore;
        $attempt->save();

        // Get grading status
        $test = $attempt->test;
        $totalItems = $test->items()->count();
        $gradedItems = $attempt->submissions()->whereNotNull('score')->count();
        $needsManualGrading = $totalItems > $gradedItems;

        return [
            'attempt' => $attempt->fresh(['submissions.testItem.itemable']),
            'total_score' => $totalScore,
            'max_possible_score' => $test->total_points,
            'needs_manual_grading' => $needsManualGrading,
            'graded_items' => $gradedItems,
            'total_items' => $totalItems,
        ];
    }

    /**
     * Grade a specific item submission (for manual grading)
     */
    public function gradeSubmission(
        TestItemSubmission $submission,
        int $score,
        ?string $feedback = null
    ): TestItemSubmission {
        $testItem = $submission->testItem;

        // Validate score doesn't exceed item points
        if ($score > $testItem->points) {
            throw new \Exception("Score cannot exceed maximum points ({$testItem->points})");
        }

        $submission->score = $score;
        $submission->is_correct = $score === $testItem->points;
        $submission->feedback = $feedback;
        $submission->save();

        // Update attempt total score and status
        $this->updateAttemptScore($submission->attempt);

        return $submission;
    }

    /**
     * Update attempt total score after grading
     */
    private function updateAttemptScore(TestAttempt $attempt): void
    {
        $totalScore = $attempt->submissions()
            ->whereNotNull('score')
            ->sum('score');

        $attempt->total_score = $totalScore;

        // Check if all items are graded
        $test = $attempt->test;
        $totalItems = $test->items()->count();
        $gradedItems = $attempt->submissions()->whereNotNull('score')->count();

        if ($gradedItems === $totalItems && $attempt->status === 'submitted') {
            $attempt->status = 'graded';
        }

        $attempt->save();
    }

    /**
     * Get test statistics for a student
     */
    public function getStudentTestStats(User $student, Test $test): array
    {
        $attempts = $test->attempts()
            ->where('student_id', $student->id)
            ->orderBy('attempt_number', 'desc')
            ->get();

        $bestScore = $attempts->where('status', 'graded')
            ->max('total_score');

        return [
            'total_attempts' => $attempts->count(),
            'attempts_remaining' => max(0, $test->max_attempts - $attempts->count()),
            'best_score' => $bestScore,
            'max_possible_score' => $test->total_points,
            'best_percentage' => $bestScore ? round(($bestScore / $test->total_points) * 100, 2) : 0,
            'latest_attempt' => $attempts->first(),
        ];
    }

    /**
     * Get test statistics for teacher
     */
    public function getTestStatisticsForTeacher(Test $test): array
    {
        $totalStudents = $test->students()->count();
        $attemptedStudents = $test->attempts()
            ->distinct('student_id')
            ->count();
        $completedAttempts = $test->attempts()
            ->whereIn('status', ['submitted', 'graded'])
            ->count();

        $averageScore = $test->attempts()
            ->where('status', 'graded')
            ->avg('total_score');

        $highestScore = $test->attempts()
            ->where('status', 'graded')
            ->max('total_score');

        $lowestScore = $test->attempts()
            ->where('status', 'graded')
            ->min('total_score');

        return [
            'total_students_assigned' => $totalStudents,
            'students_attempted' => $attemptedStudents,
            'students_not_attempted' => $totalStudents - $attemptedStudents,
            'total_attempts' => $test->attempts()->count(),
            'completed_attempts' => $completedAttempts,
            'average_score' => $averageScore ? round($averageScore, 2) : 0,
            'highest_score' => $highestScore ?? 0,
            'lowest_score' => $lowestScore ?? 0,
            'max_possible_score' => $test->total_points,
        ];
    }

    /**
     * Assign students to test
     */
    public function assignStudents(Test $test, array $studentIds): void
    {
        // If test has a course, validate students are enrolled
        if ($test->course_id) {
            $enrolledStudents = DB::table('course_enrollments')
                ->where('course_id', $test->course_id)
                ->whereIn('user_id', $studentIds)
                ->pluck('user_id')
                ->toArray();

            $notEnrolled = array_diff($studentIds, $enrolledStudents);
            
            if (!empty($notEnrolled)) {
                throw new \Exception('Some students are not enrolled in the course: ' . implode(', ', $notEnrolled));
            }
        }

        $test->students()->syncWithoutDetaching($studentIds);
    }

    /**
     * Remove students from test
     */
    public function removeStudents(Test $test, array $studentIds): void
    {
        $test->students()->detach($studentIds);
    }

    /**
     * Add items to test
     */
    public function addTestItems(Test $test, array $items): void
    {
        foreach ($items as $item) {
            $testItem = TestItem::create([
                'test_id' => $test->id,
                'itemable_type' => $item['itemable_type'],
                'itemable_id' => $item['itemable_id'],
                'order' => $item['order'] ?? 0,
                'points' => $item['points'] ?? 0,
            ]);

            // Update test total points
            $test->total_points += $testItem->points;
        }

        $test->save();
    }

    /**
     * Add existing challenge to test
     */
    public function addExistingChallengeToTest(Test $test, Challenge $challenge, int $points, ?int $order = null): TestItem
    {
        $challengeable = $challenge->challengeable;

        $testItem = TestItem::create([
            'test_id' => $test->id,
            'itemable_type' => get_class($challengeable),
            'itemable_id' => $challengeable->id,
            'order' => $order ?? $test->items()->count() + 1,
            'points' => $points,
        ]);

        $test->total_points += $points;
        $test->save();

        return $testItem;
    }

    /**
     * Create quiz question for test
     */
    public function createQuizQuestion(Test $test, array $data): TestItem
    {
        $question = QuizQuestion::create([
            'activity_id' => null, // For test questions
            'question' => $data['question'],
            'type' => $data['type'],
            'options' => json_encode($data['options'] ?? []),
            'correct_answer' => $data['correct_answer'],
            'explanation' => $data['explanation'] ?? null,
            'points' => $data['points'] ?? 1,
            'order' => $data['order'] ?? 0,
        ]);

        $testItem = TestItem::create([
            'test_id' => $test->id,
            'itemable_type' => QuizQuestion::class,
            'itemable_id' => $question->id,
            'order' => $data['item_order'] ?? $test->items()->count() + 1,
            'points' => $data['points'] ?? 1,
        ]);

        // Update test total points
        $test->total_points += $testItem->points;
        $test->save();

        return $testItem->load('itemable');
    }

    /**
     * Create essay question for test
     */
    public function createEssayQuestion(Test $test, array $data): TestItem
    {
        $essay = EssayQuestion::create([
            'question' => $data['question'],
            'min_words' => $data['min_words'] ?? null,
            'max_words' => $data['max_words'] ?? null,
            'rubric' => $data['rubric'] ?? null,
            'max_points' => $data['points'] ?? 10,
        ]);

        $testItem = TestItem::create([
            'test_id' => $test->id,
            'itemable_type' => EssayQuestion::class,
            'itemable_id' => $essay->id,
            'order' => $data['order'] ?? $test->items()->count() + 1,
            'points' => $data['points'] ?? 10,
        ]);

        $test->total_points += $testItem->points;
        $test->save();

        return $testItem->load('itemable');
    }

    /**
     * Create coding challenge for test
     */
    public function createCodingChallengeForTest(Test $test, array $data): TestItem
    {
        DB::beginTransaction();

        try {
            // Create CodingChallenge
            $codingChallenge = CodingChallenge::create([
                'problem_statement' => $data['problem_statement'],
                'test_cases'        => $data['test_cases'],
            ]);

             $languagesToAttach = [];
            foreach ($data['programming_languages'] as $langData) {
                $languagesToAttach[$langData['language_id']] = [
                    'starter_code' => $langData['starter_code'] ?? null,
                ];
            }
            $codingChallenge->programmingLanguages()->attach($languagesToAttach);
            
            $testItem = TestItem::create([
                'test_id' => $test->id,
                'itemable_type' => CodingChallenge::class,
                'itemable_id' => $codingChallenge->id,
                'order' => $data['order'] ?? $test->items()->count() + 1,
                'points' => $data['points'] ?? 10,
            ]);

            $test->total_points += $testItem->points;
            $test->save();

            DB::commit();

            return $testItem->load('itemable');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update test item
     */
    public function updateTestItem(TestItem $testItem, array $data): TestItem
    {
        $oldPoints = $testItem->points;
        
        $testItem->update($data);

        // Update test total points if points changed
        if (isset($data['points']) && $data['points'] !== $oldPoints) {
            $test = $testItem->test;
            $test->total_points = $test->total_points - $oldPoints + $data['points'];
            $test->save();
        }

        return $testItem;
    }

    /**
     * Update quiz question
     */
    public function updateQuizQuestion(TestItem $testItem, array $data): TestItem
    {
        $quizQuestion = $testItem->itemable;
        
        if (!$quizQuestion instanceof QuizQuestion) {
            throw new \Exception('Test item is not a quiz question.');
        }

        // Update quiz question fields
        $questionData = [];
        if (isset($data['question'])) {
            $questionData['question'] = $data['question'];
        }
        if (isset($data['type'])) {
            $questionData['type'] = $data['type'];
        }
        if (isset($data['options'])) {
            $questionData['options'] = json_encode($data['options']);
        }
        if (isset($data['correct_answer'])) {
            $questionData['correct_answer'] = $data['correct_answer'];
        }
        if (isset($data['explanation'])) {
            $questionData['explanation'] = $data['explanation'];
        }

        if (!empty($questionData)) {
            $quizQuestion->update($questionData);
        }

        // Update test item fields (order, points)
        $itemData = [];
        if (isset($data['order'])) {
            $itemData['order'] = $data['order'];
        }
        if (isset($data['points'])) {
            $oldPoints = $testItem->points;
            $itemData['points'] = $data['points'];
            
            // Update test total points
            $test = $testItem->test;
            $test->total_points = $test->total_points - $oldPoints + $data['points'];
            $test->save();
        }

        if (!empty($itemData)) {
            $testItem->update($itemData);
        }

        return $testItem->fresh('itemable');
    }

    /**
     * Update essay question
     */
    public function updateEssayQuestion(TestItem $testItem, array $data): TestItem
    {
        $essayQuestion = $testItem->itemable;
        
        if (!$essayQuestion instanceof EssayQuestion) {
            throw new \Exception('Test item is not an essay question.');
        }

        // Update essay question fields
        $questionData = [];
        if (isset($data['question'])) {
            $questionData['question'] = $data['question'];
        }
        if (isset($data['min_words'])) {
            $questionData['min_words'] = $data['min_words'];
        }
        if (isset($data['max_words'])) {
            $questionData['max_words'] = $data['max_words'];
        }
        if (isset($data['rubric'])) {
            $questionData['rubric'] = $data['rubric'];
        }
        if (isset($data['max_points'])) {
            $questionData['max_points'] = $data['max_points'];
        }

        if (!empty($questionData)) {
            $essayQuestion->update($questionData);
        }

        // Update test item fields (order, points)
        $itemData = [];
        if (isset($data['order'])) {
            $itemData['order'] = $data['order'];
        }
        if (isset($data['points'])) {
            $oldPoints = $testItem->points;
            $itemData['points'] = $data['points'];
            
            // Update test total points
            $test = $testItem->test;
            $test->total_points = $test->total_points - $oldPoints + $data['points'];
            $test->save();
        }

        if (!empty($itemData)) {
            $testItem->update($itemData);
        }

        return $testItem->fresh('itemable');
    }

    /**
     * Update coding challenge
     */
    public function updateCodingChallenge(TestItem $testItem, array $data): TestItem
    {
        DB::beginTransaction();

        try {
            $codingChallenge = $testItem->itemable;
            
            if (!$codingChallenge instanceof CodingChallenge) {
                throw new \Exception('Test item is not a coding challenge.');
            }

            // Update coding challenge fields
            $challengeData = [];
            if (isset($data['problem_statement'])) {
                $challengeData['problem_statement'] = $data['problem_statement'];
            }
            if (isset($data['test_cases'])) {
                $challengeData['test_cases'] = $data['test_cases'];
            }

            if (!empty($challengeData)) {
                $codingChallenge->update($challengeData);
            }

            // Update programming languages with starter code
            if (isset($data['programming_languages'])) {
                $languagesToSync = [];
                foreach ($data['programming_languages'] as $langData) {
                    $languagesToSync[$langData['language_id']] = [
                        'starter_code' => $langData['starter_code'] ?? null,
                    ];
                }
                $codingChallenge->programmingLanguages()->sync($languagesToSync);
            }

            // Update test item fields (order, points)
            $itemData = [];
            if (isset($data['order'])) {
                $itemData['order'] = $data['order'];
            }
            if (isset($data['points'])) {
                $oldPoints = $testItem->points;
                $itemData['points'] = $data['points'];
                
                // Update test total points
                $test = $testItem->test;
                $test->total_points = $test->total_points - $oldPoints + $data['points'];
                $test->save();
            }

            if (!empty($itemData)) {
                $testItem->update($itemData);
            }

            DB::commit();

            return $testItem->fresh('itemable');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update CTF challenge
     */
    public function updateCtfChallenge(TestItem $testItem, array $data): TestItem
    {
        $ctfChallenge = $testItem->itemable;
        
        if (!$ctfChallenge instanceof CtfChallenge) {
            throw new \Exception('Test item is not a CTF challenge.');
        }

        // Update CTF challenge fields
        $challengeData = [];
        if (isset($data['description'])) {
            $challengeData['description'] = $data['description'];
        }
        if (isset($data['flag'])) {
            $challengeData['flag'] = $data['flag'];
        }
        if (isset($data['hints'])) {
            $challengeData['hints'] = $data['hints'];
        }
        if (isset($data['category_id'])) {
            $challengeData['category_id'] = $data['category_id'];
        }

        if (!empty($challengeData)) {
            $ctfChallenge->update($challengeData);
        }

        // Update test item fields (order, points)
        $itemData = [];
        if (isset($data['order'])) {
            $itemData['order'] = $data['order'];
        }
        if (isset($data['points'])) {
            $oldPoints = $testItem->points;
            $itemData['points'] = $data['points'];
            
            // Update test total points
            $test = $testItem->test;
            $test->total_points = $test->total_points - $oldPoints + $data['points'];
            $test->save();
        }

        if (!empty($itemData)) {
            $testItem->update($itemData);
        }

        return $testItem->fresh('itemable');
    }

    /**
     * Remove test item
     */
    public function removeTestItem(TestItem $testItem): void
    {
        $test = $testItem->test;
        $test->total_points -= $testItem->points;
        $test->save();

        $testItem->delete();
    }

    /**
     * Get pending submissions for grading
     */
    public function getPendingSubmissions(Test $test): array
    {
        $submissions = TestItemSubmission::whereHas('attempt', function ($query) use ($test) {
            $query->where('test_id', $test->id)
                ->whereIn('status', ['submitted', 'graded']);
        })
        ->whereNull('score')
        ->with(['attempt.student', 'testItem.itemable'])
        ->get()
        ->groupBy(function ($submission) {
            return $submission->attempt->student_id;
        });

        return $submissions->toArray();
    }
}

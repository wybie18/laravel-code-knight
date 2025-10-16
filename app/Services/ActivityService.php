<?php
namespace App\Services;

use App\Models\Activity;
use App\Models\ProgrammingLanguage;
use App\Models\User;
use App\Models\UserActivitySubmission;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    private CourseProgressService $progressService;
    private Judge0TestRunner $testRunner;
    private LevelService $levelService;
    private UserActivityService $userActivityService;

    public function __construct(CourseProgressService $progressService, Judge0TestRunner $testRunner, LevelService $levelService, UserActivityService $userActivityService)
    {
        $this->progressService     = $progressService;
        $this->testRunner          = $testRunner;
        $this->levelService        = $levelService;
        $this->userActivityService = $userActivityService;
    }

    public function submitCode(string $activityId, int $languageId, string $userCode, User $user): array
    {
        $activity              = Activity::findOrFail($activityId);
        $language              = ProgrammingLanguage::findOrFail($languageId);
        $codingActivityProblem = $activity->codingActivityProblem;

        $alreadySolved = $this->checkIfAlreadySolved($activityId, $user->id);

        $results = $this->testRunner->runActivityTestCases($codingActivityProblem, $language, $userCode);

        $allTestsPassed = $results['passed'];

        $this->saveCodeSubmission($activity, $user->id, $userCode, $results);

        if ($allTestsPassed && ! $alreadySolved) {
            $this->progressService->markActivityCompleted($user, $activity);
            $description = "Completed Coding Activity: {$activity->title}";
        }
        $this->levelService->addXp($user, $activity->exp_reward, $description, $activity);
        $this->userActivityService->logActivity($user, "code_activity_submission", $activity);

        return $results;
    }

    /**
     * Submit answers for a quiz activity
     */
    public function submitQuiz(string $activityId, array $questionAnswers, User $user): array
    {
        $activity = Activity::findOrFail($activityId);

        if ($activity->type !== 'quiz') {
            throw new \InvalidArgumentException('Activity is not a quiz type');
        }

        return DB::transaction(function () use ($activity, $questionAnswers, $user) {
            $previousSubmission = UserActivitySubmission::where('activity_id', $activity->id)
                ->where('user_id', $user->id)
                ->latest()
                ->first();

            $previouslyCorrectQuestions = [];
            if ($previousSubmission) {
                $previouslyCorrectQuestions = $previousSubmission->answer['correctly_answered_questions'] ?? [];
            }

            $correctCount               = 0;
            $totalQuestions             = $activity->quizQuestions()->count();
            $questionResults            = [];
            $newXpAwarded               = 0;
            $correctlyAnsweredQuestions = $previouslyCorrectQuestions; // Start with previous correct answers

            foreach ($activity->quizQuestions as $question) {
                $userAnswer = $questionAnswers[$question->id] ?? null;
                $isCorrect  = $this->isQuizAnswerCorrect($question, $userAnswer);

                if ($isCorrect) {
                    $correctCount++;
                }

                $wasAlreadyCorrect = in_array($question->id, $previouslyCorrectQuestions);

                if ($isCorrect && ! $wasAlreadyCorrect && $question->points > 0) {
                    $description = "Correct Answer: Question #{$question->order} in {$question->activity->title}";
                    $this->levelService->addXp($user, $question->points, $description, $question);
                    $newXpAwarded += $question->points;
                }

                if ($isCorrect && ! $wasAlreadyCorrect) {
                    $correctlyAnsweredQuestions[] = $question->id;
                }

                $questionResults[$question->id] = [
                    'user_answer'             => $userAnswer,
                    'is_correct'              => $isCorrect,
                    'was_already_correct'     => $wasAlreadyCorrect,
                    'xp_awarded_this_attempt' => ($isCorrect && ! $wasAlreadyCorrect) ? $question->points : 0,
                    'points'                  => $question->points,
                ];
            }

            $scorePercentage = $totalQuestions > 0 ? ($correctCount / $totalQuestions) * 100 : 0;
            $allCorrect      = $correctCount === $totalQuestions;

            $firstPerfectScore = $allCorrect && (! $previousSubmission || ! $previousSubmission->is_correct);

            $this->saveQuizSubmission(
                $activity,
                $user->id,
                $questionAnswers,
                $correctCount,
                $totalQuestions,
                $scorePercentage,
                $allCorrect,
                array_unique($correctlyAnsweredQuestions)
            );

            if (! $previousSubmission) {
                $this->progressService->markActivityCompleted($user, $activity);
            }

            if ($firstPerfectScore && $activity->exp_reward > 0) {
                $description = "Completed Quiz: {$activity->title}";
                $this->levelService->addXp($user, $activity->exp_reward, $description, $activity);
            }
            $this->userActivityService->logActivity($user, "quiz_activity_submission", $activity);

            return [
                'passed'              => $allCorrect,
                'correct_count'       => $correctCount,
                'total_questions'     => $totalQuestions,
                'score_percentage'    => $scorePercentage,
                'question_results'    => $questionResults,
                'new_xp_awarded'      => $newXpAwarded,
                'first_perfect_score' => $firstPerfectScore,
            ];
        });
    }

    private function checkIfAlreadySolved(int $activityId, int $userId): bool
    {
        return UserActivitySubmission::where('activity_id', $activityId)
            ->where('user_id', $userId)
            ->where('is_correct', true)
            ->exists();
    }

    private function saveCodeSubmission(Activity $activity, int $userId, string $userCode, array $results)
    {
        return UserActivitySubmission::create([
            'activity_id' => $activity->id,
            'user_id'     => $userId,
            'answer'      => [
                'code'         => $userCode,
                'total_cases'  => $results['total_cases'] ?? 0,
                'passed_cases' => $results['passed_cases'] ?? 0,
            ],
            'is_correct'  => $results['passed'],
        ]);
    }

    private function saveQuizSubmission(
        Activity $activity,
        int $userId,
        array $questionAnswers,
        int $correctCount,
        int $totalQuestions,
        float $scorePercentage,
        bool $allCorrect
    ): UserActivitySubmission {
        return UserActivitySubmission::create([
            'activity_id' => $activity->id,
            'user_id'     => $userId,
            'answer'      => [
                'answers'          => $questionAnswers,
                'correct_count'    => $correctCount,
                'total_questions'  => $totalQuestions,
                'score_percentage' => round($scorePercentage, 2),
            ],
            'is_correct'  => $allCorrect,
        ]);
    }

    /**
     * Check if quiz answer is correct
     */
    private function isQuizAnswerCorrect($question, $userAnswer): bool
    {
        $correctAnswer = json_decode($question->correct_answer, true);

        return $correctAnswer === $userAnswer;
    }
}

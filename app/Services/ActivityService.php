<?php
namespace App\Services;

use App\Models\Activity;
use App\Models\ProgrammingLanguage;
use App\Models\User;
use App\Models\UserActivitySubmission;

class ActivityService
{
    private CourseProgressService $progressService;
    private Judge0TestRunner $testRunner;

    public function __construct(CourseProgressService $progressService, Judge0TestRunner $testRunner)
    {
        $this->progressService = $progressService;
        $this->testRunner = $testRunner;
    }

    public function submitCode(string $activityId, int $languageId, string $userCode, User $user): array
    {
        $activity              = Activity::findOrFail($activityId);
        $language              = ProgrammingLanguage::findOrFail($languageId);
        $codingActivityProblem = $activity->codingActivityProblem;

        $alreadySolved = $this->checkIfAlreadySolved($activityId, $user->id);

        $results = $this->testRunner->runActivityTestCases($codingActivityProblem, $language, $userCode);

        $allTestsPassed = $results['passed'];

        $this->saveSubmission($activity, $user->id, $userCode, $results);

        if ($allTestsPassed && ! $alreadySolved) {
            $this->progressService->markActivityCompleted($user, $activity);
        }

        return $results;
    }

    private function checkIfAlreadySolved(int $activityId, int $userId): bool
    {
        return UserActivitySubmission::where('activity_id', $activityId)
            ->where('user_id', $userId)
            ->where('is_correct', true)
            ->exists();
    }

    private function saveSubmission(Activity $activity, int $userId, string $userCode, array $results)
    {
        return UserActivitySubmission::create([
            'activity_id' => $activity->id,
            'user_id'     => $userId,
            'answer'      => $userCode,
            'is_correct'  => $results['passed'],
        ]);
    }
}

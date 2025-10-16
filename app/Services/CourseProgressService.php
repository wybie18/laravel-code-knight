<?php
namespace App\Services;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserActivityProgress;
use App\Models\UserCourseProgress;
use App\Models\UserLessonProgress;
use App\Models\UserModuleProgress;

class CourseProgressService
{
    /**
     * Get all course content ordered by module and content order
     */
    private $levelService;
    private $userActivityService;

    public function __construct(LevelService $levelService, UserActivityService $userActivityService)
    {
        $this->levelService = $levelService;
        $this->userActivityService = $userActivityService;
    }

    public function getAllCourseContentOrdered(Course $course): array
    {
        $allContent = [];

        $modules = $course->modules()->orderBy('order')->get();

        foreach ($modules as $module) {
            $lessons    = $module->lessons()->orderBy('order')->get();
            $activities = $module->activities()->orderBy('order')->get();

            // Merge lessons and activities, then sort by order
            $moduleContent = [];

            foreach ($lessons as $lesson) {
                $moduleContent[] = [
                    'type'      => 'lesson',
                    'id'        => $lesson->id,
                    'order'     => $lesson->order,
                    'model'     => $lesson,
                    'module_id' => $module->id,
                ];
            }

            foreach ($activities as $activity) {
                $moduleContent[] = [
                    'type'      => 'activity',
                    'id'        => $activity->id,
                    'order'     => $activity->order,
                    'model'     => $activity,
                    'module_id' => $module->id,
                ];
            }

            usort($moduleContent, function ($a, $b) {
                return $a['order'] <=> $b['order'];
            });

            $allContent = array_merge($allContent, $moduleContent);
        }

        return $allContent;
    }

    /**
     * Find the index of specific content in the ordered content array
     */
    private function findContentIndex(array $allContent, string $type, int $id): ?int
    {
        foreach ($allContent as $index => $content) {
            if ($content['type'] === $type && $content['id'] === $id) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Check if specific content is completed by user
     */
    private function isContentCompleted(User $user, array $content): bool
    {
        if ($content['type'] === 'lesson') {
            return UserLessonProgress::where('user_id', $user->id)
                ->where('lesson_id', $content['id'])
                ->exists();
        } elseif ($content['type'] === 'activity') {
            return UserActivityProgress::where('user_id', $user->id)
                ->where('activity_id', $content['id'])
                ->where('is_completed', true)
                ->exists();
        }

        return false;
    }

    /**
     * Mark lesson as completed for user
     */
    public function markLessonCompleted(User $user, Lesson $lesson): void
    {
        $this->userActivityService->logActivity($user, "lesson_completion", $lesson);

        $this->enrollUserInCourse($user, $lesson->module->course);

        $progress = UserLessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($progress && $progress->completed_at) {
            return;
        }
        
        UserLessonProgress::firstOrCreate([
            'user_id'   => $user->id,
            'lesson_id' => $lesson->id,
        ], [
            'completed_at' => now(),
        ]);

        $this->levelService->addXp($user, $lesson->exp_reward, "Completed Lesson: {$lesson->title}", $lesson);

        $this->updateModuleProgress($user, $lesson->module);
        $this->updateCourseProgress($user, $lesson->module->course);
    }

    /**
     * Mark activity as completed for user
     */
    public function markActivityCompleted(User $user, Activity $activity): void
    {
        $this->userActivityService->logActivity($user, "activity_completion", $activity);
        $this->enrollUserInCourse($user, $activity->module->course);

        $progress = UserActivityProgress::where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->first();
        
        if ($progress && $progress->is_completed) {
            return;
        }

        UserActivityProgress::updateOrCreate([
            'user_id'     => $user->id,
            'activity_id' => $activity->id,
        ], [
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $this->levelService->addXp($user, $activity->exp_reward, "Completed Activity: {$activity->title}", $activity);

        $this->updateModuleProgress($user, $activity->module);
        $this->updateCourseProgress($user, $activity->module->course);
    }

    /**
     * Update module progress for user
     */
    public function updateModuleProgress(User $user, CourseModule $module): void
    {
        $totalLessons    = $module->lessons()->count();
        $totalActivities = $module->activities()->where('is_required', true)->count();
        $totalContent    = $totalLessons + $totalActivities;

        $completedLessons = UserLessonProgress::where('user_id', $user->id)
            ->whereHas('lesson', function ($query) use ($module) {
                $query->where('course_module_id', $module->id);
            })->count();

        $completedActivities = UserActivityProgress::where('user_id', $user->id)
            ->where('is_completed', true)
            ->whereHas('activity', function ($query) use ($module) {
                $query->where('course_module_id', $module->id)
                    ->where('is_required', true);
            })->count();

        $completedContent   = $completedLessons + $completedActivities;
        $progressPercentage = $totalContent > 0 ? round(($completedContent / $totalContent) * 100) : 0;

        UserModuleProgress::updateOrCreate([
            'user_id'          => $user->id,
            'course_module_id' => $module->id,
        ], [
            'progress_percentage' => $progressPercentage,
            'completed_at'        => $progressPercentage === 100 ? now() : null,
        ]);
    }

    /**
     * Update course progress for user
     */
    public function updateCourseProgress(User $user, Course $course): void
    {
        $allContent = $this->getAllCourseContentOrdered($course);
        $totalContent = count($allContent);
        
        if ($totalContent === 0) {
            return;
        }

        $completedContent = 0;
        foreach ($allContent as $content) {
            if ($this->isContentCompleted($user, $content)) {
                $completedContent++;
            }
        }

        $progressPercentage = round(($completedContent / $totalContent) * 100);

        UserCourseProgress::updateOrCreate([
            'user_id'   => $user->id,
            'course_id' => $course->id,
        ], [
            'progress_percentage' => $progressPercentage,
            'completed_at'        => $progressPercentage === 100 ? now() : null,
            'started_at'          => UserCourseProgress::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->value('started_at') ?? now(),
        ]);

        if ($progressPercentage === 100) {
            $this->markCourseCompleted($user, $course);
            $this->levelService->addXp($user, $course->completion_exp_reward, "Completed Course: {$course->title}", $course);
        }
    }

    /**
     * Get previous content navigation data for current content
     * Returns data needed for frontend routing instead of backend URLs
     */
    public function getPrevContentData($currentContent): ?array
    {
        if ($currentContent instanceof Lesson) {
            $course      = $currentContent->module->course;
            $contentType = 'lesson';
            $contentId   = $currentContent->id;
        } elseif ($currentContent instanceof Activity) {
            $course      = $currentContent->module->course;
            $contentType = 'activity';
            $contentId   = $currentContent->id;
        } else {
            return null;
        }

        $allContent   = $this->getAllCourseContentOrdered($course);
        $currentIndex = $this->findContentIndex($allContent, $contentType, $contentId);

        if ($currentIndex !== null && $currentIndex > 0) {
            $prevContent = $allContent[$currentIndex - 1];
            $module      = CourseModule::select('id', 'slug', 'title')->find($prevContent['module_id']);

            $contentType = $prevContent['type'] === 'lesson'
                ? 'lesson'
                : $prevContent['model']->type;

            return [
                'type'    => $contentType,
                'content' => [
                    'id'    => $prevContent['id'],
                    'slug'  => $prevContent['model']->slug,
                    'title' => $prevContent['model']->title,
                ],
                'module'  => [
                    'id'    => $module->id,
                    'slug'  => $module->slug,
                    'title' => $module->title,
                ],
            ];
        }

        return null;
    }

    /**
     * Get next content navigation data after completing current content
     * Returns data needed for frontend routing instead of backend URLs
     */
    public function getNextContentData(User $user, $currentContent): ?array
    {
        if ($currentContent instanceof Lesson) {
            $course      = $currentContent->module->course;
            $contentType = 'lesson';
            $contentId   = $currentContent->id;
        } elseif ($currentContent instanceof Activity) {
            $course      = $currentContent->module->course;
            $contentType = 'activity';
            $contentId   = $currentContent->id;
        } else {
            return null;
        }

        $allContent   = $this->getAllCourseContentOrdered($course);
        $currentIndex = $this->findContentIndex($allContent, $contentType, $contentId);

        if ($currentIndex !== null && isset($allContent[$currentIndex + 1])) {
            $nextContent = $allContent[$currentIndex + 1];
            $module      = CourseModule::select('id', 'slug', 'title')->find($nextContent['module_id']);

            $contentType = $nextContent['type'] === 'lesson'
                ? 'lesson'
                : $nextContent['model']->type;

            return [
                'type'    => $contentType,
                'content' => [
                    'id'    => $nextContent['id'],
                    'slug'  => $nextContent['model']->slug,
                    'title' => $nextContent['model']->title,
                ],
                'module'  => [
                    'id'    => $module->id,
                    'slug'  => $module->slug,
                    'title' => $module->title,
                ],
                'is_course_complete' => false,
            ];
        }

        if ($currentIndex === count($allContent) - 1) {
            $isCourseComplete = $this->isCourseFullyCompleted($user, $course);
            
            return [
                'type'               => 'congratulations',
                'is_course_complete' => $isCourseComplete,
                'course'             => [
                    'id'    => $course->id,
                    'slug'  => $course->slug,
                    'title' => $course->title,
                ],
            ];
        }

        return null;
    }

    /**
     * Get complete navigation context for current content
     * Useful for frontend to understand current position and available navigation
     */
    public function getNavigationContext($currentContent): array
    {
        if ($currentContent instanceof Lesson) {
            $course      = $currentContent->module->course;
            $contentType = 'lesson';
            $contentId   = $currentContent->id;
        } elseif ($currentContent instanceof Activity) {
            $course      = $currentContent->module->course;
            $contentType = 'activity';
            $contentId   = $currentContent->id;
        } else {
            return [];
        }

        $allContent   = $this->getAllCourseContentOrdered($course);
        $currentIndex = $this->findContentIndex($allContent, $contentType, $contentId);

        if ($currentIndex === null) {
            return [];
        }

        $context = [
            'has_previous' => $currentIndex > 0,
            'has_next'     => $currentIndex < count($allContent) - 1,
            'previous'     => null,
            'next'         => null,
        ];

        if ($context['has_previous']) {
            $prevContent     = $allContent[$currentIndex - 1];
            $prevModule      = CourseModule::select('id', 'slug', 'title')->find($prevContent['module_id']);
            $prevContentType = $prevContent['type'] === 'lesson'
                ? 'lesson'
                : $prevContent['model']->type;

            $context['previous'] = [
                'type'    => $prevContentType,
                'content' => [
                    'id'    => $prevContent['id'],
                    'slug'  => $prevContent['model']->slug,
                    'title' => $prevContent['model']->title,
                ],
                'module'  => [
                    'id'    => $prevModule->id,
                    'slug'  => $prevModule->slug,
                    'title' => $prevModule->title,
                ],
            ];
        }

        if ($context['has_next']) {
            $nextContent     = $allContent[$currentIndex + 1];
            $nextModule      = CourseModule::select('id', 'slug', 'title')->find($nextContent['module_id']);
            $nextContentType = $nextContent['type'] === 'lesson'
                ? 'lesson'
                : $nextContent['model']->type;

            $context['next'] = [
                'type'    => $nextContentType,
                'content' => [
                    'id'    => $nextContent['id'],
                    'slug'  => $nextContent['model']->slug,
                    'title' => $nextContent['model']->title,
                ],
                'module'  => [
                    'id'    => $nextModule->id,
                    'slug'  => $nextModule->slug,
                    'title' => $nextModule->title,
                ],
            ];
        }

        return $context;
    }

    /**
     * Get user progress for a specific course
     */
    public function getUserCourseProgress(User $user, Course $course): array
    {
        $allContent   = $this->getAllCourseContentOrdered($course);
        $progressData = [];

        foreach ($allContent as $content) {
            $isCompleted = $this->isContentCompleted($user, $content);

            $progressData[] = [
                'content'      => $content,
                'is_completed' => $isCompleted,
            ];
        }

        return $progressData;
    }

    /**
     * Get overall course statistics for user
     */
    public function getCourseStatistics(User $user, Course $course): array
    {
        $allContent       = $this->getAllCourseContentOrdered($course);
        $totalContent     = count($allContent);
        $completedContent = 0;

        $totalLessons    = 0;
        $totalActivities = 0;

        foreach ($allContent as $content) {
            if ($content['type'] === 'lesson') {
                $totalLessons++;
            } else {
                $totalActivities++;
            }

            if ($this->isContentCompleted($user, $content)) {
                $completedContent++;
            }
        }

        $progressPercentage = $totalContent > 0 ? round(($completedContent / $totalContent) * 100) : 0;

        return [
            'total_content'       => $totalContent,
            'completed_content'   => $completedContent,
            'total_lessons'       => $totalLessons,
            'total_activities'    => $totalActivities,
            'progress_percentage' => $progressPercentage,
            'is_completed'        => $progressPercentage === 100,
        ];
    }

    /**
     * Get the current active content for user in a course
     */
    public function getCurrentActiveContent(User $user, Course $course): ?array
    {
        $allContent = $this->getAllCourseContentOrdered($course);
        $data       = [];

        if (empty($allContent)) {
            return null;
        }

        foreach ($allContent as $content) {
            $isCompleted = $this->isContentCompleted($user, $content);

            if (! $isCompleted) {
                $module = $course->modules()
                    ->select('id', 'title', 'slug')
                    ->where('id', $content['module_id'])
                    ->first();

                if (! $module) {
                    continue;
                }

                if ($content['type'] === 'lesson') {
                    $data = [
                        'id'     => $content['id'],
                        'title'  => $content['model']->title,
                        'slug'   => $content['model']->slug,
                        'order'  => $content['order'],
                        'type'   => 'lesson',
                        'module' => $module,
                    ];
                } else {
                    $data = [
                        'id'     => $content['id'],
                        'title'  => $content['model']->title,
                        'slug'   => $content['model']->slug,
                        'order'  => $content['order'],
                        'type'   => $content['model']->type,
                        'module' => $module,
                    ];
                }
                return $data;
            }
        }
        return null;
    }

    /**
     * Enroll user in course if not already enrolled
     */
    public function enrollUserInCourse(User $user, Course $course): bool
    {
        $enrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($enrollment) {
            return false;
        }

        CourseEnrollment::create([
            'user_id'     => $user->id,
            'course_id'   => $course->id,
            'enrolled_at' => now(),
            'status'      => 'active',
        ]);

        return true;
    }

    /**
     * Check if user is enrolled in course
     */
    public function isUserEnrolled(User $user, Course $course): bool
    {
        return CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();
    }

    /**
     * Mark course as completed for user (update enrollment)
     */
    public function markCourseCompleted(User $user, Course $course): void
    {
        CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->update([
                'completed_at' => now(),
                'status'       => 'completed',
            ]);
    }

    private function isCourseFullyCompleted(User $user, Course $course): bool
    {
        $allContent = $this->getAllCourseContentOrdered($course);
        
        if (empty($allContent)) {
            return false;
        }

        foreach ($allContent as $content) {
            if (!$this->isContentCompleted($user, $content)) {
                return false;
            }
        }

        return true;
    }
}

<?php
namespace App\Services;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserActivityProgress;
use App\Models\UserCourseProgress;
use App\Models\UserLessonProgress;
use App\Models\UserModuleProgress;

class CourseProgressService
{
    /**
     * Get all course content ordered by module and content order
     */
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
        UserLessonProgress::firstOrCreate([
            'user_id'   => $user->id,
            'lesson_id' => $lesson->id,
        ], [
            'completed_at' => now(),
        ]);

        $this->updateModuleProgress($user, $lesson->courseModule);
        $this->updateCourseProgress($user, $lesson->courseModule->course);
    }

    /**
     * Mark activity as completed for user
     */
    public function markActivityCompleted(User $user, Activity $activity): void
    {
        UserActivityProgress::updateOrCreate([
            'user_id'     => $user->id,
            'activity_id' => $activity->id,
        ], [
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $this->updateModuleProgress($user, $activity->courseModule);
        $this->updateCourseProgress($user, $activity->courseModule->course);
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
        $totalModules     = $course->modules()->count();
        $completedModules = UserModuleProgress::where('user_id', $user->id)
            ->whereHas('courseModule', function ($query) use ($course) {
                $query->where('course_id', $course->id);
            })
            ->whereNotNull('completed_at')
            ->count();

        $progressPercentage = $totalModules > 0 ? round(($completedModules / $totalModules) * 100) : 0;

        UserCourseProgress::updateOrCreate([
            'user_id'   => $user->id,
            'course_id' => $course->id,
        ], [
            'progress_percentage' => $progressPercentage,
            'completed_at'        => $progressPercentage === 100 ? now() : null,
        ]);
    }

    /**
     * Get next content URL after completing current content
     */
    public function getNextContentUrl(User $user, $currentContent): ?string
    {
        if ($currentContent instanceof Lesson) {
            $course      = $currentContent->courseModule->course;
            $contentType = 'lesson';
            $contentId   = $currentContent->id;
        } elseif ($currentContent instanceof Activity) {
            $course      = $currentContent->courseModule->course;
            $contentType = 'activity';
            $contentId   = $currentContent->id;
        } else {
            return null;
        }

        $allContent   = $this->getAllCourseContentOrdered($course);
        $currentIndex = $this->findContentIndex($allContent, $contentType, $contentId);

        if ($currentIndex !== null && isset($allContent[$currentIndex + 1])) {
            $nextContent = $allContent[$currentIndex + 1];

            if ($nextContent['type'] === 'lesson') {
                return route('courses.modules.lessons.show', [$course->slug, $nextContent['module_id'], $nextContent['id']]);
            } else {
                return route('courses.modules.activities.show', [$course->slug, $nextContent['module_id'], $nextContent['id']]);
            }
        }

        return route('courses.show', $course->id);
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
}

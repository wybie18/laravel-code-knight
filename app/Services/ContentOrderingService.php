<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Facades\Auth;

class ContentOrderingService
{
    /**
     * Get content organized by modules for UI display
     */
    public function getContentByModules(Course $course): array
    {
        $moduleData = [];
        $user = Auth::user();

        $modules = $course->modules()->with(['lessons', 'activities'])->orderBy('order')->get();

        foreach ($modules as $module) {
            $lessons = $module->lessons->sortBy('order');
            $activities = $module->activities->sortBy('order');

            $content = [];
            
            foreach ($lessons as $lesson) {
                $isCompleted = $user ? $user->lessonProgress()->where('lesson_id', $lesson->id)->exists() : false;

                $content[] = [
                    'type' => 'lesson',
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'slug' => $lesson->slug,
                    'order' => $lesson->order,
                    'exp_reward' => $lesson->exp_reward,
                    'estimated_duration' => $lesson->estimated_duration,
                    'is_completed' => $isCompleted,
                ];
            }

            foreach ($activities as $activity) {
                $isCompleted = $user ? $user->activityProgress()
                    ->where('activity_id', $activity->id)
                    ->where('is_completed', true)
                    ->exists() : false;

                $content[] = [
                    'type' => 'activity',
                    'id' => $activity->id,
                    'title' => $activity->title,
                    'description' => $activity->description,
                    'activity_type' => $activity->type,
                    'order' => $activity->order,
                    'exp_reward' => $activity->exp_reward,
                    'is_required' => $activity->is_required,
                    'is_completed' => $isCompleted,
                ];
            }

            usort($content, function($a, $b) {
                return $a['order'] <=> $b['order'];
            });

            $moduleData[] = [
                'id' => $module->id,
                'title' => $module->title,
                'slug' => $module->slug,
                'description' => $module->description,
                'order' => $module->order,
                'content' => $content,
            ];
        }

        return $moduleData;
    }
}
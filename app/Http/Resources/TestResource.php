<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'duration_minutes' => $this->duration_minutes,
            'total_points' => $this->total_points,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'shuffle_questions' => $this->shuffle_questions,
            'show_results_immediately' => $this->show_results_immediately,
            'allow_review' => $this->allow_review,
            'max_attempts' => $this->max_attempts,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'teacher' => $this->whenLoaded('teacher', function () {
                return [
                    'id' => $this->teacher->id,
                    'username' => $this->teacher->username,
                    'first_name' => $this->teacher->first_name,
                    'last_name' => $this->teacher->last_name,
                    'email' => $this->teacher->email,
                ];
            }),

            'course' => $this->whenLoaded('course', function () {
                return [
                    'id' => $this->course->id,
                    'title' => $this->course->title,
                    'slug' => $this->course->slug,
                    'programming_language' => $this->course->programmingLanguage,
                ];
            }),

            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'order' => $item->order,
                        'points' => $item->points,
                        'itemable_type' => $item->itemable_type,
                        'itemable_id' => $item->itemable_id,
                        'itemable' => $this->formatItemable($item->itemable),
                    ];
                });
            }),

            // Counts
            'items_count' => $this->when(isset($this->items_count), $this->items_count),
            'students_count' => $this->when(isset($this->students_count), $this->students_count),
            'attempts_count' => $this->when(isset($this->attempts_count), $this->attempts_count),

            // Additional data
            'student_stats' => $this->when(isset($this->student_stats), $this->student_stats),
            'can_start_attempt' => $this->when(isset($this->can_start_attempt), $this->can_start_attempt),
        ];
    }

    /**
     * Format the itemable data based on its type
     */
    private function formatItemable($itemable): ?array
    {
        if (!$itemable) {
            return null;
        }

        $baseData = [
            'id' => $itemable->id,
            'type' => class_basename($itemable),
        ];

        // Format based on type
        if ($itemable instanceof \App\Models\QuizQuestion) {
            $quizData = [
                'question' => $itemable->question,
                'type_detail' => $itemable->type,
                'options' => $itemable->options,
                'points' => $itemable->points,
                'explanation' => $itemable->explanation,
            ];

            // Include correct_answer if user is teacher/creator or admin
            $user = auth()->user();
            if ($user && ($this->teacher_id === $user->id || $user->tokenCan('admin:*') || $user->tokenCan('tests:view'))) {
                $quizData['correct_answer'] = $itemable->correct_answer;
            }

            return array_merge($baseData, $quizData);
        }

        if ($itemable instanceof \App\Models\EssayQuestion) {
            return array_merge($baseData, [
                'question' => $itemable->question,
                'rubric' => $itemable->rubric,
                'max_points' => $itemable->max_points,
                'min_words' => $itemable->min_words,
                'max_words' => $itemable->max_words,
            ]);
        }

        if ($itemable instanceof \App\Models\CodingChallenge) {
            // Load programming languages with starter code
            $itemable->loadMissing('programmingLanguages');
            
            return array_merge($baseData, [
                'problem_statement' => $itemable->problem_statement,
                'test_cases' => $itemable->test_cases,
                'programming_languages' => $itemable->programmingLanguages->map(function ($lang) {
                    return [
                        'id' => $lang->id,
                        'name' => $lang->name,
                        'starter_code' => $lang->pivot->starter_code,
                    ];
                }),
            ]);
        }

        if ($itemable instanceof \App\Models\CtfChallenge) {
            $ctfData = [
                'file_paths' => $itemable->file_paths,
                'category_id' => $itemable->category_id,
            ];

            // Include flag if user is teacher/creator or admin
            $user = auth()->user();
            if ($user && ($this->teacher_id === $user->id || $user->tokenCan('admin:*') || $user->tokenCan('tests:view'))) {
                $ctfData['flag'] = $itemable->flag;
            }

            return array_merge($baseData, $ctfData);
        }

        return $baseData;
    }
}

<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'type'        => $this->type,
            'exp_reward'  => $this->exp_reward,
            'order'       => $this->order,
            'is_required' => $this->is_required,
            'problem'     => $this->when(
                $this->type === 'code',
                fn() => new CodingActivityProblemResource($this->codingActivityProblem)
            ),
            'questions'   => $this->when(
                $this->type === 'quiz',
                fn() => QuizQuestionResource::collection($this->quizQuestions)
            ),
            'submissions' => UserActivitySubmissionResource::collection($this->whenLoaded('activitySubmissions')),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,

            'module'      => new CourseModuleResource($this->whenLoaded('module')),
            'course'      => new CourseResource($this->whenLoaded('course')),
        ];
    }
}

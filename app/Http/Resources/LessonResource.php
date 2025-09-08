<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'title'              => $this->title,
            'slug'               => $this->slug,
            'content'            => $this->content,
            'exp_reward'         => $this->exp_reward,
            'estimated_duration' => $this->estimated_duration,
            'order'              => $this->order,
            'is_published'       => $this->is_published,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,

            // Relationships
            'module'             => new CourseModuleResource($this->whenLoaded('module')),
            'course'             => new CourseResource($this->whenLoaded('course')),
            'prerequisites'      => LessonResource::collection($this->whenLoaded('prerequisites')),
            'progress'           => new UserLessonProgressResource($this->whenLoaded('userProgress')),

            // Computed fields
            'activities_count'   => $this->when($this->activities_count !== null, $this->activities_count),
            'is_accessible'      => $this->when(isset($this->is_accessible), $this->is_accessible),
            'is_completed'       => $this->when(isset($this->is_completed), $this->is_completed),
        ];
    }
}

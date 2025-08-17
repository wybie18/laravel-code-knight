<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'title'                      => $this->title,
            'slug'                       => $this->slug,
            'description'                => $this->description,
            'short_description'          => $this->short_description,
            'objectives'                 => $this->objectives,
            'thumbnail'                  => $this->thumbnail,
            'exp_reward'                 => $this->exp_reward,
            'estimated_duration'         => $this->estimated_duration,
            'is_published'               => $this->is_published,
            'created_at'                 => $this->created_at,
            'updated_at'                 => $this->updated_at,

            // Relationships
            'difficulty'                 => new DifficultyResource($this->whenLoaded('difficulty')),
            'category'                   => new CourseCategoryResource($this->whenLoaded('category')),
            'modules'                    => CourseModuleResource::collection($this->whenLoaded('modules')),
            'skill_tags'                 => SkillTagResource::collection($this->whenLoaded('skillTags')),
            'enrollment'                 => new CourseEnrollmentResource($this->whenLoaded('userEnrollment')),
            'progress'                   => new UserCourseProgressResource($this->whenLoaded('userProgress')),

            // Computed fields
            'lessons_count'              => $this->when($this->lessons_count !== null, $this->lessons_count),
            'modules_count'              => $this->when($this->modules_count !== null, $this->modules_count),
            'enrolled_users_count'       => $this->when($this->enrolled_users_count !== null, $this->enrolled_users_count),
        ];
    }
}

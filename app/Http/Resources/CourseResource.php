<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isCreator = $user && $this->created_by === $user->id;
        $isAdmin = $user && $user->tokenCan('admin:*');

        return [
            'id'                   => $this->id,
            'title'                => $this->title,
            'slug'                 => $this->slug,
            'description'          => $this->description,
            'short_description'    => $this->short_description,
            'objectives'           => $this->objectives,
            'requirements'         => $this->requirements,
            'thumbnail'            => $this->thumbnail ? url(Storage::url($this->thumbnail)) : '',
            'exp_reward'           => $this->exp_reward,
            'estimated_duration'   => $this->estimated_duration,
            'is_published'         => $this->is_published,
            'visibility'           => $this->visibility,
            
            'course_code'          => $this->when(
                $isCreator || $isAdmin || ($user && $this->whenLoaded('userEnrollment')),
                $this->course_code
            ),
            
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,

            // Relationships
            'difficulty'           => new DifficultyResource($this->whenLoaded('difficulty')),
            'category'             => new CourseCategoryResource($this->whenLoaded('category')),
            'modules'              => CourseModuleResource::collection($this->whenLoaded('modules')),
            'skill_tags'           => SkillTagResource::collection($this->whenLoaded('skillTags')),
            'enrollment'           => new CourseEnrollmentResource($this->whenLoaded('userEnrollment')),
            'progress'             => new UserCourseProgressResource($this->whenLoaded('currentUserProgress')),
            'programming_language' => new ProgrammingLanguageResource($this->whenLoaded('programmingLanguage')),
            
            'creator'              => $this->when(
                $this->relationLoaded('creator'),
                function () {
                    return [
                        'id' => $this->creator->id,
                        'username' => $this->creator->username,
                        'first_name' => $this->creator->first_name,
                        'last_name' => $this->creator->last_name,
                    ];
                }
            ),

            // Computed fields
            'lessons_count'        => $this->when($this->relationLoaded('modules'), function () {
                return DB::table('lessons')
                    ->whereIn('course_module_id', $this->modules->pluck('id'))
                    ->count();
            }),
            'modules_count'        => $this->whenHas('modules_count'),
            'enrolled_users_count' => $this->whenHas('enrollments_count'),
            
            // Permissions
            'is_creator'           => $isCreator,
        ];
    }
}

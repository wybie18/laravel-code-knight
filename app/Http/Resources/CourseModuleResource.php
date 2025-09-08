<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseModuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'slug'           => $this->slug,
            'description'    => $this->description,
            'order'          => $this->order,
            'exp_reward'     => $this->exp_reward,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,

            // Relationships
            'course'         => new CourseResource($this->whenLoaded('course')),
            'lessons'        => LessonResource::collection($this->whenLoaded('lessons')),
            'activities'     => ActivityResource::collection($this->whenLoaded('activities')),

            // Computed fields
            'lessons_count'  => $this->when($this->lessons_count !== null, $this->lessons_count),
            'total_duration' => $this->when($this->total_duration !== null, $this->total_duration),
        ];
    }
}

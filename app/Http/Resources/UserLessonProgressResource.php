<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLessonProgressResource extends JsonResource
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
            'progress_percentage' => $this->progress_percentage,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'is_accessible' => $this->is_accessible,
        ];
    }
}

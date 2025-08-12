<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TypingChallengeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'text_content'            => $this->text_content,
            'target_wpm'              => $this->target_wpm,
            'target_accuracy'         => $this->target_accuracy,
            'programming_language'    => new ProgrammingLanguageResource($this->whenLoaded('programmingLanguage')),
        ];
    }
}

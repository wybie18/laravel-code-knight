<?php
namespace App\Http\Resources;

use App\Models\TypingChallenge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ChallengeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'slug'          => $this->slug,
            'description'   => $this->description,
            'points'        => $this->points,
            'hints'         => $this->hints,
            'is_solved'     => $this->is_solved ?? false,
            'difficulty'    => new DifficultyResource($this->whenLoaded('difficulty')),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'challengeable' => $this->whenLoaded('challengeable', function () {
                if ($this->challengeable_type === \App\Models\CtfChallenge::class) {
                    return new CtfChallengeResource($this->challengeable);
                } elseif ($this->challengeable_type === \App\Models\CodingChallenge::class) {
                    return new CodingChallengeResource($this->challengeable);
                } elseif ($this->challengeable_type === TypingChallenge::class) {
                    return new TypingChallengeResource($this->challengeable);
                }
                return null;
            }),
            'type'          => Str::afterLast($this->challengeable_type, '\\'),
        ];
    }
}

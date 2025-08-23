<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue; // Import MissingValue

class ProgrammingLanguageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pivot = $this->whenPivotLoaded('challenge_language', function () use ($request) {
            return [
                'starter_code'  => $this->pivot->starter_code
            ];
        });

        $languageData = [
            'id'          => $this->id,
            'name'        => $this->name,
            'version'     => $this->version,
            'language_id' => $this->language_id,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];

        if ($pivot instanceof MissingValue) {
            $pivot = [];
        }

        return array_merge($languageData, $pivot);
    }
}
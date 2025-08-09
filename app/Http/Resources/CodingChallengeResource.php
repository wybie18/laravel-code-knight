<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CodingChallengeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        $data = [
            'id'                => $this->id,
            'problem_statement' => $this->problem_statement,
            'programming_languages' => ProgrammingLanguageResource::collection($this->whenLoaded('programmingLanguages')),
        ];

        if ($request->user() && $request->user()->tokenCan('admin:*')) {
            $data['test_cases'] = json_decode($this->test_cases);
            $data['programming_languages'] = $this->whenLoaded('programmingLanguages', function () {
                return $this->programmingLanguages->map(function ($lang) {
                    return [
                        'id'           => $lang->id,
                        'name'         => $lang->name,
                        'starter_code' => $lang->pivot->starter_code,
                        'solution_code' => $lang->pivot->solution_code,
                    ];
                });
            });
        } else {
            $data['test_cases'] = null;
            $data['programming_languages'] = $this->whenLoaded('programmingLanguages', function () {
                return $this->programmingLanguages->map(function ($lang) {
                    return [
                        'id'           => $lang->id,
                        'name'         => $lang->name,
                        'starter_code' => $lang->pivot->starter_code,
                    ];
                });
            });
        }

        return $data;
    }
}

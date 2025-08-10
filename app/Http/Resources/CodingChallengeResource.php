<?php
namespace App\Http\Resources;

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
        return [
            'id'                    => $this->id,
            'problem_statement'     => $this->problem_statement,
            'test_cases'            => $this->when(
                $request->user() && $request->user()->tokenCan('admin:*'),
                json_decode($this->test_cases)
            ),
            'programming_languages' => ProgrammingLanguageResource::collection(
                $this->whenLoaded('programmingLanguages')
            ),
        ];
    }
}

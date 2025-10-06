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
        $allTestCases = $this->test_cases;
        $isAdmin      = $request->user() && $request->user()->tokenCan('admin:*');

        if ($isAdmin) {
            $testCasesToShow = $allTestCases;
        } else {
            $testCasesToShow = array_slice($allTestCases, 0, 3);
        }

        return [
            'id'                    => $this->id,
            'problem_statement'     => $this->problem_statement,
            'test_cases'            => $testCasesToShow,
            'programming_languages' => ProgrammingLanguageResource::collection(
                $this->whenLoaded('programmingLanguages')
            ),
        ];
    }
}

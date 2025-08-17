<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CodingActivityProblemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'problem_statement' => $this->problem_statement,
            'starter_code'      => $this->starter_code,
            'solution_code'     => $this->when($request->user()->tokenCan('admin:*'), $this->solution_code),
            'test_cases'        => $this->test_cases,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}

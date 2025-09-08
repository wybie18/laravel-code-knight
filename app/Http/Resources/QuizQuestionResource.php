<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizQuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $showAnswers = $this->show_answers || ($request->user() && $request->user()->tokenCan('admin:*'));
        return [
            'id'             => $this->id,
            'question'       => $this->question,
            'type'           => $this->type,
            'points'         => $this->points,
            'order'          => $this->order,
            'options'        => json_decode($this->options, true),
            'correct_answer' => json_decode($this->when($showAnswers, $this->correct_answer)),
            'explanation'    => $this->when($showAnswers, $this->explanation),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}

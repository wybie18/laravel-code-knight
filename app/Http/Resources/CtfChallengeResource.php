<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CtfChallengeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id'          => $this->id,
            'category_id' => $this->category_id,
            'category'    => new CtfCategoryResource($this->whenLoaded('category')),
            'file_paths'  => collect($this->file_paths)->map(function ($path) {
                return $path;
            }),
        ];

        if ($request->user() && $request->user()->tokenCan('admin:*')) {
            $data['flag'] = $this->flag;
        }

        return $data;
    }
}

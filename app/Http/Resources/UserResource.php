<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'student_id' => $this->student_id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'username'   => $this->username,
            'email'      => $this->email,
            'avatar'     => $this->getAvatarUrl(),
            'role'       => $this->whenLoaded('role', fn() => $this->role->name),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the avatar URL, handling both local paths and external URLs.
     *
     * @return string
     */
    protected function getAvatarUrl(): string
    {
        if (!$this->avatar) {
            return '';
        }

        // Check if avatar is already a full URL (starts with http:// or https://)
        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }

        // Otherwise, treat it as a local storage path
        return url(Storage::url($this->avatar));
    }
}

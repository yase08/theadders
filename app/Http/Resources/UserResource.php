<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'users_id' => $this->users_id,
            'fullname' => $this->fullname,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'bio' => $this->bio,
            'avatar' => $this->avatar,
        ];
    }
}

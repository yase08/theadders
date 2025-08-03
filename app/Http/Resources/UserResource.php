<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        $array = [
            'users_id' => $this->users_id,
            'fullname' => $this->fullname,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'bio' => $this->bio,
            'avatar' => $this->avatar,
            'firebase_uid' => $this->firebase_uid,
            'email_verified_at' => $this->email_verified_at,
        ];

        if ($this->additional['stats'] ?? null) {
            $array['stats'] = $this->additional['stats'];
        }

        return $array;
    }
}

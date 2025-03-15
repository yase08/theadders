<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'fullname' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.image' => 'The uploaded file must be an image.',
            'avatar.max' => 'Profile photo size must not exceed 5MB.',
            'avatar.mimes' => 'Profile photo must be of type: jpeg, png, jpg.',
        ];        
    }
}
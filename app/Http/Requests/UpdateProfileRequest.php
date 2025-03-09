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
            'avatar.image' => 'File yang diunggah harus berupa gambar.',
            'avatar.max' => 'Size foto profile tidak boleh lebih dari 5MB.',
            'avatar.mimes' => 'Tipe foto profile harus: jpeg, png, jpg.',
        ];
    }
}
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason'      => 'required|in:harassment,hate_speech,scam,impersonation,inappropriate_content,spam,other',
            'description' => 'required_if:reason,other|nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required'         => 'Reason is required.',
            'reason.in'               => 'Invalid report reason.',
            'description.required_if' => 'Description is required when reason is Other.',
            'description.max'         => 'Description may not be greater than 500 characters.',
        ];
    }
}

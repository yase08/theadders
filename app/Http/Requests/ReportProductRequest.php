<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason'      => 'required|in:spam,inappropriate,fake,prohibited,wrong_category,other',
            'title'       => 'required_if:reason,other|nullable|string|max:100',
            'description' => 'required_if:reason,other|nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required'      => 'Reason is required.',
            'reason.in'            => 'Invalid report reason.',
            'title.required_if'    => 'Title is required when reason is Other.',
            'title.max'            => 'Title may not be greater than 100 characters.',
            'description.required_if' => 'Description is required when reason is Other.',
            'description.max'      => 'Description may not be greater than 500 characters.',
        ];
    }
}

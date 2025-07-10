<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [  
            'category_id' => 'nullable|integer|exists:mst_category,category_id',
            'category_sub_id' => 'nullable|integer|exists:mst_category_sub,category_sub_id',
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:recent,relevance', // Removed price_high, price_low
            'size' => 'nullable|string|in:small,medium,large',
            'price_range' => 'nullable|string|in:1-30,20-60,50-110,100-210,200-500',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'The selected category was not found.',
            'category_sub_id.exists' => 'The selected subcategory was not found.',
            'sort.in' => 'The sort parameter is invalid.',
            'size.in' => 'Size must be small, medium, or large.',
            'price_range.in' => 'The price range is invalid.',
            'per_page.integer' => 'Per page must be a number.',
            'per_page.min' => 'Per page must be at least 1.',
        ];        
    }
}
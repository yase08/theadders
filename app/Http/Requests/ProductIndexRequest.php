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
            'sort' => 'nullable|string|in:recent,price_high,price_low,relevance',
            'size' => 'nullable|string|in:small,medium,large',
            'price_range' => 'nullable|string|in:1-30,20-60,50-110,100-210,200-500',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Kategori yang dipilih tidak ditemukan',
            'category_sub_id.exists' => 'Sub Kategori yang dipilih tidak ditemukan',
            'sort.in' => 'Parameter sort tidak sesuai',
            'size.in' => 'Size harus berisi small, medium, large',
            'price_range.in' => 'Range harga tidak sesuai',
            'per_page.integer' => 'Per page harus berupa angka',
            'per_page.min' => 'Per page minimal 1',
        ];
    }
}
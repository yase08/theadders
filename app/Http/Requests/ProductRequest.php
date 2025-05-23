<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna diizinkan untuk membuat request ini.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mendapatkan aturan validasi yang berlaku untuk request ini.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'category_id' => 'nullable|integer|exists:mst_category,category_id',
            'category_sub_id' => 'nullable|integer|exists:mst_category_sub,category_sub_id',
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbail' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'price' => 'nullable|numeric|min:0',
            'item_codition' => 'nullable|integer|in:1,2,3',
            'product_images' => 'nullable|array',
            'product_images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ];
    }

    /**
     * Mendapatkan pesan kesalahan yang digunakan oleh validasi.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_name.required' => 'Product name is required.',
            'product_name.string' => 'Product name must be a text.',
            'product_name.max' => 'Product name must not exceed 255 characters.',
            'category_id.exists' => 'The selected category is invalid.',
            'category_sub_id.exists' => 'The selected subcategory is invalid.',
            'price.numeric' => 'Price must be a number.',
            'item_codition.in' => 'Item condition is invalid.',
        ];
    }
}

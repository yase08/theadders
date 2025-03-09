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
    public function rules(): array
    {
        return [
            'category_id' => 'nullable|integer|exists:mst_category,category_id',
            'category_sub_id' => 'nullable|integer|exists:mst_category_sub,category_sub_id',
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbail' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'price' => 'nullable|numeric|min:0',
            'end_price' => 'nullable|numeric|min:0',
            'year_release' => 'nullable|digits:4',
            'buy_release' => 'nullable|digits:4',
            'item_codition' => 'nullable|integer|in:1,2,3',
            'view_count' => 'nullable|integer|min:0',
            'status' => 'nullable|in:0,1,2',
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
            'product_name.required' => 'Nama produk wajib diisi.',
            'product_name.string' => 'Nama produk harus berupa teks.',
            'product_name.max' => 'Nama produk tidak boleh lebih dari 255 karakter.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid.',
            'category_sub_id.exists' => 'Subkategori yang dipilih tidak valid.',
            'price.numeric' => 'Harga harus berupa angka.',
            'end_price.numeric' => 'Harga akhir harus berupa angka.',
            'item_codition.in' => 'Kondisi barang tidak valid.',
            'status.in' => 'Status tidak valid.',
            'view_count.integer' => 'Jumlah tampilan harus berupa angka.',
        ];
    }
}

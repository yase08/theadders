<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class ExchangeRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      'product_id' =>
      [
        'required',
        'exists:tabel_product,product_id',
        function ($attribute, $value, $fail) {
          if (!Product::where('product_id', $value)->where('author', auth()->id())->exists()) {
            $fail('Produk ini bukan milik Anda.');
          }
        },
      ],
      'to_product_id' => [
        'required',
        'exists:tabel_product,product_id',
        function ($attribute, $value, $fail) {
          if (Product::where('product_id', $value)->where('author', auth()->id())->exists()) {
            $fail('Produk tujuan tidak boleh milik Anda sendiri.');
          }
        },
      ],
      'to_user_id' => [
        'required',
        'exists:users,users_id',
        function ($attribute, $value, $fail) {
          if ($value == auth()->id()) {
            $fail('Anda tidak bisa bertukar dengan diri sendiri.');
          }
        },
      ],
    ];
  }

  /**
   * Custom error messages.
   */
  public function messages(): array
  {
    return [
      'product_id.required'    => 'Product ID diperlukan.',
      'product_id.integer'     => 'Product ID harus berupa angka.',
      'product_id.exists'      => 'Produk yang dipilih tidak ada.',

      'to_product_id.required' => 'Target Product ID diperlukan.',
      'to_product_id.integer'  => 'Target Product ID harus berupa angka.',
      'to_product_id.exists'   => 'Produk tujuan yang dipilih tidak ada.',

      'to_user_id.required'    => 'Penerima user ID diperlukan.',
      'to_user_id.integer'     => 'Penerima user ID harus berupa angka.',
      'to_user_id.exists'      => 'Penerima user yang dipilih tidak ada.',
    ];
  }
}

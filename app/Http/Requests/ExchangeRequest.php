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
            $fail('This product does not belong to you.');
          }
        },
      ],
      'to_product_id' => [
        'required',
        'exists:tabel_product,product_id',
        function ($attribute, $value, $fail) {
          if (Product::where('product_id', $value)->where('author', auth()->id())->exists()) {
            $fail('The target product cannot be your own.');
          }
        },
      ],
      'to_user_id' => [
        'required',
        'exists:users,users_id',
        function ($attribute, $value, $fail) {
          if ($value == auth()->id()) {
            $fail('You cannot exchange with yourself.');
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
      'product_id.required'    => 'Product ID is required.',
      'product_id.integer'     => 'Product ID must be a number.',
      'product_id.exists'      => 'The selected product does not exist.',

      'to_product_id.required' => 'Target Product ID is required.',
      'to_product_id.integer'  => 'Target Product ID must be a number.',
      'to_product_id.exists'   => 'The selected target product does not exist.',

      'to_user_id.required'    => 'Recipient user ID is required.',
      'to_user_id.integer'     => 'Recipient user ID must be a number.',
      'to_user_id.exists'      => 'The selected recipient user does not exist.',
    ];
  }
}

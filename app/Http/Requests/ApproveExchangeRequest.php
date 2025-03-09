<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveExchangeRequest extends FormRequest
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
      'exchange_id' => 'required|integer|exists:trs_exchange,exchange_id',
    ];
  }

  public function validationData()
  {
    return ['exchange_id' => $this->route('exchange_id')];
  }

  /**
   * Custom error messages.
   */
  public function messages(): array
  {
    return [
      'exchange_id.required' => 'Exchange ID diperlukan.',
      'exchange_id.integer'  => 'Exchange ID harus berupa angka.',
      'exchange_id.exists'   => 'Exchange yang dipilih tidak ada.',
    ];
  }
}

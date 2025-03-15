<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetExchangeByIdRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

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

  public function messages(): array
  {
    return [
      'exchange_id.required' => 'Exchange ID is required.',
      'exchange_id.integer'  => 'Exchange ID must be a number.',
      'exchange_id.exists'   => 'The selected exchange does not exist.',
    ];
  }
}

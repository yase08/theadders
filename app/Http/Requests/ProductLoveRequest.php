<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductLoveRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'product_id' => 'required|exists:products,product_id',
            'user_id' => 'sometimes|exists:users,users_id',
            'status' => 'sometimes|in:0,1,2'
        ];
    }

    public function messages()
    {
        return [
            'product_id.required' => 'Product ID is required',
            'product_id.exists' => 'Product does not exist',
            'user_id.exists' => 'User does not exist',
            'status.in' => 'Invalid status value'
        ];
    }
}
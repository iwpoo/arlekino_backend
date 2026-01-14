<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderUpdateRequest extends FormRequest
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
            'status'          => 'sometimes|string|in:pending,assembling,completed,canceled',
            'payment_method'  => 'sometimes|string|in:card,cash',
            'shipping_address'=> 'sometimes|string',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.string' => 'Статус должен быть строкой.',
            'status.in' => 'Статус должен быть одним из: pending, assembling, completed, canceled.',
            'payment_method.string' => 'Способ оплаты должен быть строкой.',
            'payment_method.in' => 'Способ оплаты должен быть картой или наличными.',
            'shipping_address.string' => 'Адрес доставки должен быть строкой.',
        ];
    }
}
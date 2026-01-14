<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderStatusUpdateRequest extends FormRequest
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
            'status' => 'required|string|in:pending,assembling,shipped,completed,canceled',
            'qr_token' => 'nullable|string',
            'seller_order_id' => 'nullable|integer|exists:seller_orders,id,order_id,' . $this->route('order')->id,
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
            'status.required' => 'Статус обязателен.',
            'status.string' => 'Статус должен быть строкой.',
            'status.in' => 'Статус должен быть одним из: pending, assembling, shipped, completed, canceled.',
            'qr_token.string' => 'QR токен должен быть строкой.',
            'seller_order_id.integer' => 'ID заказа продавца должен быть целым числом.',
            'seller_order_id.exists' => 'Выбранный заказ продавца не существует.',
        ];
    }
}
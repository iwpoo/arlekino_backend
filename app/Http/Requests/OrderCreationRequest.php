<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderCreationRequest extends FormRequest
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
            'payment_method'    => 'required|in:card,cash',
            'items'             => 'required|array|min:1',
            'items.*.id'        => 'required|integer|exists:cart_items,id',
            'items.*.quantity'  => 'required|integer|min:1',
            'card_id'           => 'nullable|required_if:payment_method,card|exists:bank_cards,id,user_id,' . auth()->id(),
            'address_id'        => 'required|integer',
            'currency'          => 'nullable|string|size:3',
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
            'payment_method.required' => 'Способ оплаты обязателен.',
            'payment_method.in' => 'Способ оплаты должен быть картой или наличными.',
            'items.required' => 'Товары обязательны.',
            'items.array' => 'Товары должны быть массивом.',
            'items.min' => 'Требуется хотя бы один товар.',
            'items.*.id.required' => 'Каждый товар должен иметь ID.',
            'items.*.id.integer' => 'ID товара должен быть целым числом.',
            'items.*.id.exists' => 'Выбранный товар не существует.',
            'items.*.quantity.required' => 'Каждый товар должен иметь количество.',
            'items.*.quantity.integer' => 'Количество товара должно быть целым числом.',
            'items.*.quantity.min' => 'Количество товара должно быть не менее 1.',
            'card_id.exists' => 'Выбранная карта не существует.',
            'address_id.required' => 'ID адреса обязателен.',
            'address_id.integer' => 'ID адреса должен быть целым числом.',
            'currency.string' => 'Валюта должна быть строкой.',
            'currency.size' => 'Валюта должна содержать 3 символа.',
        ];
    }
}
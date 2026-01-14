<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankCardCreateRequest extends FormRequest
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
            'card_number' => 'required|string|size:16',
            'card_holder' => 'required|string|max:255',
            'expiry_month' => 'required|numeric|between:1,12',
            'expiry_year' => 'required|numeric|min:' . date('y'),
            'cvv' => 'required|numeric|digits:3',
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
            'card_number.required' => 'Номер карты обязателен.',
            'card_number.string' => 'Номер карты должен быть строкой.',
            'card_number.size' => 'Номер карты должен содержать 16 символов.',
            'card_holder.required' => 'Владелец карты обязателен.',
            'card_holder.string' => 'Владелец карты должен быть строкой.',
            'card_holder.max' => 'Владелец карты не может превышать 255 символов.',
            'expiry_month.required' => 'Месяц истечения обязателен.',
            'expiry_month.numeric' => 'Месяц истечения должен быть числом.',
            'expiry_month.between' => 'Месяц истечения должен быть от 1 до 12.',
            'expiry_year.required' => 'Год истечения обязателен.',
            'expiry_year.numeric' => 'Год истечения должен быть числом.',
            'expiry_year.min' => 'Год истечения недействителен.',
            'cvv.required' => 'CVV обязателен.',
            'cvv.numeric' => 'CVV должен быть числом.',
            'cvv.digits' => 'CVV должен состоять из 3 цифр.',
        ];
    }
}
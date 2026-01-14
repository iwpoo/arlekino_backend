<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserAddressCreateRequest extends FormRequest
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
            'country' => 'required|string|max:100',
            'region' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'street' => 'required|string|max:255',
            'house' => 'required|string|max:20',
            'apartment' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:20',
            'is_default' => 'boolean'
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
            'country.required' => 'Страна обязательна.',
            'country.string' => 'Страна должна быть строкой.',
            'country.max' => 'Страна не может превышать 100 символов.',
            'region.string' => 'Регион должен быть строкой.',
            'region.max' => 'Регион не может превышать 100 символов.',
            'city.required' => 'Город обязателен.',
            'city.string' => 'Город должен быть строкой.',
            'city.max' => 'Город не может превышать 100 символов.',
            'street.required' => 'Улица обязательна.',
            'street.string' => 'Улица должна быть строкой.',
            'street.max' => 'Улица не может превышать 255 символов.',
            'house.required' => 'Дом обязателен.',
            'house.string' => 'Дом должен быть строкой.',
            'house.max' => 'Дом не может превышать 20 символов.',
            'apartment.string' => 'Квартира должна быть строкой.',
            'apartment.max' => 'Квартира не может превышать 20 символов.',
            'postal_code.string' => 'Почтовый индекс должен быть строкой.',
            'postal_code.max' => 'Почтовый индекс не может превышать 20 символов.',
            'is_default.boolean' => 'По умолчанию должен быть true или false.',
        ];
    }
}
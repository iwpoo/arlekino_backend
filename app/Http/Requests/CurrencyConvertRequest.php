<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CurrencyConvertRequest extends FormRequest
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
            'amount' => 'required|numeric|min:0',
            'from' => 'nullable|string|size:3',
            'to' => 'required|string|size:3',
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
            'amount.required' => 'Сумма обязательна.',
            'amount.numeric' => 'Сумма должна быть числом.',
            'amount.min' => 'Сумма должна быть не менее 0.',
            'from.string' => 'Исходная валюта должна быть строкой.',
            'from.size' => 'Исходная валюта должна содержать 3 символа.',
            'to.required' => 'Целевая валюта обязательна.',
            'to.string' => 'Целевая валюта должна быть строкой.',
            'to.size' => 'Целевая валюта должна содержать 3 символа.',
        ];
    }
}
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrecalculateRequest extends FormRequest
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
            'item_ids'   => 'required|array',
            'item_ids.*' => 'exists:cart_items,id,user_id,' . auth()->id(),
            'currency'   => 'nullable|string|size:3',
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
            'item_ids.required' => 'ID товаров обязательны.',
            'item_ids.array' => 'ID товаров должны быть массивом.',
            'item_ids.*.exists' => 'Один или несколько выбранных товаров не существуют.',
            'currency.string' => 'Валюта должна быть строкой.',
            'currency.size' => 'Валюта должна содержать 3 символа.',
        ];
    }
}
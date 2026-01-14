<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductUpdateRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'content' => 'nullable|string|max:5000',
            'price' => 'required|numeric|min:0',
            'discountType' => ['nullable', 'string'],
            'discountValue' => 'nullable|integer|min:0',
            'quantity' => 'required|integer|min:0',
            'condition' => 'required|string',
            'refund' => 'nullable|boolean',
            'inStock' => 'nullable|boolean',
            'points' => 'required|string',
            'category_id' => 'required|integer|exists:categories,id',
            'attributes' => 'required|string',
            'existing_media_ids' => 'nullable|string', // JSON array of existing media IDs to keep
            'price_currency' => 'nullable|string|size:3',
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
            'title.required' => 'Заголовок обязателен.',
            'title.string' => 'Заголовок должен быть строкой.',
            'title.max' => 'Заголовок не может превышать 255 символов.',
            'content.string' => 'Содержание должно быть строкой.',
            'content.max' => 'Содержание не может превышать 5000 символов.',
            'price.required' => 'Цена обязательна.',
            'price.numeric' => 'Цена должна быть числом.',
            'price.min' => 'Цена должна быть не менее 0.',
            'discountType.string' => 'Тип скидки должен быть строкой.',
            'discountValue.integer' => 'Значение скидки должно быть целым числом.',
            'discountValue.min' => 'Значение скидки должно быть не менее 0.',
            'quantity.required' => 'Количество обязательно.',
            'quantity.integer' => 'Количество должно быть целым числом.',
            'quantity.min' => 'Количество должно быть не менее 0.',
            'condition.required' => 'Состояние обязательно.',
            'condition.string' => 'Состояние должно быть строкой.',
            'refund.boolean' => 'Возврат должен быть true или false.',
            'inStock.boolean' => 'В наличии должен быть true или false.',
            'points.required' => 'Баллы обязательны.',
            'points.string' => 'Баллы должны быть строкой.',
            'category_id.required' => 'ID категории обязателен.',
            'category_id.integer' => 'ID категории должен быть целым числом.',
            'category_id.exists' => 'Выбранная категория не существует.',
            'attributes.required' => 'Атрибуты обязательны.',
            'attributes.string' => 'Атрибуты должны быть строкой.',
            'existing_media_ids.string' => 'Существующие ID медиа должны быть строкой.',
            'price_currency.string' => 'Валюта цены должна быть строкой.',
            'price_currency.size' => 'Валюта цены должна содержать 3 символа.',
        ];
    }
}
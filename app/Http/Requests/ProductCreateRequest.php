<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductCreateRequest extends FormRequest
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
            'files' => 'required|array',
            'files.*' => 'file|mimes:jpeg,png,jpg,gif,mp4',
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
            'views_count' => 'nullable|integer|min:0',
            'shares_count' => 'nullable|integer|min:0',
            'likes_count' => 'nullable|integer|min:0',
            'reviews_count' => 'nullable|integer|min:0',
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
            'files.required' => 'Файлы обязательны.',
            'files.array' => 'Файлы должны быть массивом.',
            'files.*.file' => 'Каждый файл должен быть действительным файлом.',
            'files.*.mimes' => 'Файлы должны быть типа jpeg, png, jpg, gif или mp4.',
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
            'views_count.integer' => 'Количество просмотров должно быть целым числом.',
            'views_count.min' => 'Количество просмотров должно быть не менее 0.',
            'shares_count.integer' => 'Количество репостов должно быть целым числом.',
            'shares_count.min' => 'Количество репостов должно быть не менее 0.',
            'likes_count.integer' => 'Количество лайков должно быть целым числом.',
            'likes_count.min' => 'Количество лайков должно быть не менее 0.',
            'reviews_count.integer' => 'Количество отзывов должно быть целым числом.',
            'reviews_count.min' => 'Количество отзывов должно быть не менее 0.',
            'price_currency.string' => 'Валюта цены должна быть строкой.',
            'price_currency.size' => 'Валюта цены должна содержать 3 символа.',
        ];
    }
}
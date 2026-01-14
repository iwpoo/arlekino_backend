<?php

namespace App\Http\Requests;

use App\Enums\DiscountType;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class PromotionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only sellers can create promotions
        return $this->user()->isSeller();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'discount_type' => ['required', 'string', DiscountType::rule()],
            'discount_value' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'exists:products,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Название акции обязательно для заполнения.',
            'discount_type.required' => 'Тип скидки обязателен для выбора.',
            'discount_value.required' => 'Размер скидки обязателен для заполнения.',
            'discount_value.min' => 'Размер скидки не может быть отрицательным.',
            'end_date.after' => 'Дата окончания должна быть позже даты начала.',
            'product_ids.required' => 'Необходимо выбрать хотя бы один товар.',
            'product_ids.*.exists' => 'Выбранный товар не существует.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateDiscountValue($validator);
            $this->validateProductOwnership($validator);
        });
    }

    /**
     * Validate discount value based on discount type.
     */
    private function validateDiscountValue($validator): void
    {
        $discountType = $this->input('discount_type');
        $discountValue = $this->input('discount_value');

        if ($discountType === 'percent' && $discountValue > 100) {
            $validator->errors()->add('discount_value', 'Процент скидки не может превышать 100%.');
        }
    }

    /**
     * Validate that all products belong to the current user.
     */
    private function validateProductOwnership($validator): void
    {
        $productIds = $this->input('product_ids', []);
        $userId = $this->user()->id;

        if (!empty($productIds)) {
            $invalidProducts = Product::whereIn('id', $productIds)
                ->where('user_id', '!=', $userId)
                ->count();

            if ($invalidProducts > 0) {
                $validator->errors()->add('product_ids', 'Вы можете добавлять в акцию только свои товары.');
            }
        }
    }
}

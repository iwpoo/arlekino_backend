<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.reason' => 'required|in:wrong_size,disliked_color_design,does_not_match_description,defective_damaged,changed_mind',
            'items.*.comment' => 'nullable|string|max:1000',
            'items.*.photos' => 'nullable|array|max:3',
            'items.*.photos.*' => 'string|url',
            'return_method' => 'required|in:SELF_RETURN,COURIER_RETURN',
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'ID заказа обязателен.',
            'order_id.exists' => 'Указанный заказ не существует.',
            'items.required' => 'Товары для возврата обязательны.',
            'items.array' => 'Товары для возврата должны быть массивом.',
            'items.min' => 'Для возврата требуется хотя бы один товар.',
            'items.*.order_item_id.required' => 'ID позиции заказа обязателен для каждого возвращаемого товара.',
            'items.*.order_item_id.exists' => 'Указанная позиция заказа не существует.',
            'items.*.reason.required' => 'Причина возврата обязательна.',
            'items.*.reason.in' => 'Указана недопустимая причина возврата.',
            'return_method.required' => 'Способ возврата обязателен.',
            'return_method.in' => 'Указан недопустимый способ возврата.',
        ];
    }
}
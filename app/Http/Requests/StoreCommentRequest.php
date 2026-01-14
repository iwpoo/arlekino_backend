<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content'   => 'required|string|max:1000',
            'parent_id' => [
                'nullable',
                'exists:comments,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Текст комментария обязателен для заполнения.',
            'content.max'      => 'Комментарий не может быть длиннее 1000 символов.',
            'parent_id.exists' => 'Родительский комментарий не найден.',
        ];
    }
}

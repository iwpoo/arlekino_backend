<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateGroupChatRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
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
            'name.required' => 'Название чата обязательно.',
            'name.string' => 'Название чата должно быть строкой.',
            'name.max' => 'Название чата не может превышать 255 символов.',
            'user_ids.required' => 'ID пользователей обязательны.',
            'user_ids.array' => 'ID пользователей должны быть массивом.',
            'user_ids.min' => 'Должен быть выбран хотя бы один пользователь.',
            'user_ids.*.exists' => 'Один или несколько выбранных пользователей не существуют.',
        ];
    }
}
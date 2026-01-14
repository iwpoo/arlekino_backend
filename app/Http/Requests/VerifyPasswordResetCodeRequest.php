<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPasswordResetCodeRequest extends FormRequest
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
            'user_id' => 'required|integer|exists:users,id',
            'code' => 'required|string|size:6',
            'login' => 'required|string',
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
            'user_id.required' => 'ID пользователя обязателен.',
            'user_id.integer' => 'ID пользователя должен быть целым числом.',
            'user_id.exists' => 'Выбранный ID пользователя не существует.',
            'code.required' => 'Код обязателен.',
            'code.size' => 'Код должен содержать 6 символов.',
            'login.required' => 'Логин обязателен.',
        ];
    }
}
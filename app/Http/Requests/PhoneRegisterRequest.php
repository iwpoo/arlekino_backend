<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PhoneRegisterRequest extends FormRequest
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
            'temp_token' => 'required|string',
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:8|confirmed',
            'city' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email',
            'role' => 'nullable|in:client,seller'
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
            'temp_token.required' => 'Временный токен обязателен.',
            'name.required' => 'Имя обязательно.',
            'username.required' => 'Имя пользователя обязательно.',
            'username.unique' => 'Это имя пользователя уже занято.',
            'password.required' => 'Пароль обязателен.',
            'password.min' => 'Пароль должен содержать не менее 8 символов.',
            'password.confirmed' => 'Пароли не совпадают.',
            'email.email' => 'Пожалуйста, укажите действительный адрес электронной почты.',
            'email.unique' => 'Этот email уже зарегистрирован.',
            'role.in' => 'Роль должна быть клиентом или продавцом.',
        ];
    }
}
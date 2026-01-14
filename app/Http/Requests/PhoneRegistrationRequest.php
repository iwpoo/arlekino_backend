<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PhoneRegistrationRequest extends FormRequest
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
            'password' => 'required|string|min:8|confirmed',
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username|max:255',
            'email' => 'nullable|email|unique:users,email|max:255', // Make email optional
            'role' => 'required|in:client,seller',
            'city' => 'nullable|string|max:255',
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
            'password.required' => 'Пароль обязателен.',
            'password.min' => 'Пароль должен содержать не менее 8 символов.',
            'password.confirmed' => 'Пароли не совпадают.',
            'name.required' => 'Имя обязательно.',
            'username.required' => 'Имя пользователя обязательно.',
            'username.unique' => 'Это имя пользователя уже занято.',
            'email.email' => 'Пожалуйста, укажите действительный адрес электронной почты.',
            'email.unique' => 'Этот email уже зарегистрирован.',
            'role.required' => 'Роль обязательна.',
            'role.in' => 'Роль должна быть клиентом или продавцом.',
        ];
    }
}
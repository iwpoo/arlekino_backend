<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
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
            'body' => 'nullable|string|max:1000',
            'file' => 'nullable|file|max:10240', // Максимум 10MB
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
            'body.string' => 'Тело сообщения должно быть строкой.',
            'body.max' => 'Тело сообщения не может превышать 1000 символов.',
            'file.file' => 'Загруженный файл должен быть действительным файлом.',
            'file.max' => 'Размер файла не может превышать 10 МБ.',
        ];
    }
}
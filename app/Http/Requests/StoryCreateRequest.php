<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoryCreateRequest extends FormRequest
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
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,mp4',
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
            'file.required' => 'Файл обязателен.',
            'file.file' => 'Файл должен быть действительным файлом.',
            'file.mimes' => 'Файл должен быть типа jpeg, png, jpg, gif или mp4.',
        ];
    }
}
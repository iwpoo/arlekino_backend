<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostCreateRequest extends FormRequest
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
            'files.*' => 'file|mimes:jpeg,png,jpg,gif,mp4|max:10240', // max 10MB
            'content' => 'nullable|string|max:5000',
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
            'files.*.max' => 'Каждый файл не может превышать 10 МБ.',
            'content.string' => 'Содержание должно быть строкой.',
            'content.max' => 'Содержание не может превышать 5000 символов.',
        ];
    }
}

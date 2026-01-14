<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewUpdateRequest extends FormRequest
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
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'video' => 'nullable|file|mimes:mp4,avi,mov|max:10240',
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
            'rating.integer' => 'Оценка должна быть целым числом.',
            'rating.min' => 'Оценка должна быть не менее 1.',
            'rating.max' => 'Оценка не может превышать 5.',
            'comment.string' => 'Комментарий должен быть строкой.',
            'comment.max' => 'Комментарий не может превышать 2000 символов.',
            'photos.array' => 'Фотографии должны быть массивом.',
            'photos.max' => 'Вы не можете загрузить более 10 фотографий.',
            'photos.*.image' => 'Каждая фотография должна быть изображением.',
            'photos.*.mimes' => 'Фотографии должны быть типа jpeg, png, jpg, gif или webp.',
            'photos.*.max' => 'Каждая фотография не может превышать 5 МБ.',
            'video.file' => 'Видео должно быть файлом.',
            'video.mimes' => 'Видео должно быть типа mp4, avi или mov.',
            'video.max' => 'Видео не может превышать 10 МБ.',
        ];
    }
}
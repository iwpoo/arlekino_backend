<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Question;
use Illuminate\Database\Seeder;

class CategoriesWithQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        // Очищаем таблицы перед заполнением
        Question::query()->delete();
        Category::query()->delete();

        // 1. Создаем корневые категории
        $electronics = Category::create(['name' => 'Электроника']);
        $clothing = Category::create(['name' => 'Одежда']);
        $books = Category::create(['name' => 'Книги']);

        // 2. Создаем подкатегории для электроники
        $smartphones = Category::create([
            'name' => 'Смартфоны',
            'parent_id' => $electronics->id
        ]);

        $laptops = Category::create([
            'name' => 'Ноутбуки',
            'parent_id' => $electronics->id
        ]);

        // 3. Создаем подкатегории для смартфонов
        $android = Category::create([
            'name' => 'Android',
            'parent_id' => $smartphones->id
        ]);

        $ios = Category::create([
            'name' => 'iOS',
            'parent_id' => $smartphones->id
        ]);

        // 4. Добавляем вопросы для Android
        Question::create([
            'question' => 'Какой бренд?',
            'type' => 'select',
            'options' => ['Samsung', 'Xiaomi', 'OnePlus'],
            'category_id' => $android->id
        ]);

        Question::create([
            'question' => 'Какой объем памяти?',
            'type' => 'number',
            'category_id' => $android->id
        ]);

        // 5. Добавляем вопросы для iOS
        Question::create([
            'question' => 'Какой объем памяти?',
            'type' => 'number',
            'category_id' => $ios->id
        ]);

        Question::create([
            'question' => 'Цвет устройства?',
            'type' => 'text',
            'category_id' => $ios->id
        ]);

        // 6. Добавляем вопросы для одежды
        $mensClothing = Category::create([
            'name' => 'Мужская',
            'parent_id' => $clothing->id
        ]);

        Question::create([
            'question' => 'Какой размер?',
            'type' => 'select',
            'options' => ['S', 'M', 'L', 'XL'],
            'category_id' => $mensClothing->id
        ]);
    }
}

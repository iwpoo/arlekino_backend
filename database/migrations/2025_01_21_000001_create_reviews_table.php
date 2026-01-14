<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('rating')->unsigned(); // 1-5 звезд
            $table->text('comment')->nullable();
            $table->json('photos')->nullable(); // Массив путей к фотографиям
            $table->string('video_path')->nullable(); // Путь к видео
            $table->boolean('is_verified_purchase')->default(false); // Проверенный покупатель
            $table->unsignedInteger('helpful_count')->default(0); // Количество "полезно"
            $table->timestamps();
            
            // Один пользователь может оставить только один отзыв на товар
            $table->unique(['user_id', 'product_id']);
            
            // Индексы для быстрого поиска
            $table->index(['product_id', 'rating']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};


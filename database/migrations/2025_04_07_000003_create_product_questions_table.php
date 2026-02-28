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
        Schema::create('product_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Кто задал вопрос
            $table->text('question');
            $table->text('answer')->nullable(); // Ответ продавца
            $table->foreignId('answered_by')->nullable()->constrained('users')->onDelete('set null'); // Кто ответил
            $table->timestamp('answered_at')->nullable();
            $table->unsignedInteger('helpful_count')->default(0); // Количество "полезно"
            $table->timestamps();
            
            // Индексы для быстрого поиска
            $table->index(['product_id', 'created_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_questions');
    }
};


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
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
            $table->boolean('is_review_post')->default(false)->after('product_id');
            
            // Индекс для быстрого поиска постов-отзывов по товару
            $table->index(['product_id', 'is_review_post']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'is_review_post']);
            $table->dropForeign(['product_id']);
            $table->dropColumn(['product_id', 'is_review_post']);
        });
    }
};


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
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('price', 10, 2); // Price per item
            $table->enum('reason', [
                'wrong_size',
                'disliked_color_design',
                'does_not_match_description',
                'defective_damaged',
                'changed_mind'
            ]);
            $table->text('comment')->nullable();
            $table->json('photos')->nullable(); // Array of photo URLs
            $table->timestamps();

            // Indexes
            $table->index('return_id');
            $table->index('order_item_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};

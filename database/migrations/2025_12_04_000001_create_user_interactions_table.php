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
        Schema::create('user_interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('target_type'); // 'product', 'post', 'user'
            $table->unsignedBigInteger('target_id');
            $table->string('interaction_type'); // 'view', 'like', 'share', 'comment', 'purchase', 'follow'
            $table->integer('weight')->default(1); // Weight of the interaction
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index(['user_id', 'created_at']);
            $table->index(['target_type', 'target_id']);
            $table->index('interaction_type');
            
            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_interactions');
    }
};
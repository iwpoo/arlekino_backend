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
        // First, let's check if the 'name' column exists, if not, add it
        if (!Schema::hasColumn('chats', 'name')) {
            Schema::table('chats', function (Blueprint $table) {
                $table->string('name')->nullable()->after('id');
            });
        }
        
        // Add indexes to the chats table
        Schema::table('chats', function (Blueprint $table) {
            $table->index('is_private');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex(['is_private']);
            $table->dropIndex(['created_at']);
            
            if (Schema::hasColumn('chats', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
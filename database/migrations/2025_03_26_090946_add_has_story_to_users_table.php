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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('stories_count')->default(0)->after('email');
            $table->timestamp('last_story_created_at')->nullable()->after('stories_count');
            $table->boolean('has_unseen_stories')->default(false)->after('last_story_created_at');
            $table->timestamp('last_story_viewed_at')->nullable()->after('has_unseen_stories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('stories_count');
            $table->dropColumn('last_story_created_at');
            $table->dropColumn('has_unseen_stories');
            $table->dropColumn('last_story_viewed_at');
        });
    }
};

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
        Schema::table('messages', function (Blueprint $table) {
            $table->string('type')->nullable()->after('body'); // 'text', 'image', 'file'
            $table->string('attachment_url')->nullable()->after('type');
            $table->string('attachment_name')->nullable()->after('attachment_url');
            $table->unsignedBigInteger('file_size')->nullable()->after('attachment_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'attachment_url', 'attachment_name', 'file_size']);
        });
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL to ensure the column is properly set as VARCHAR
        DB::statement('ALTER TABLE categories MODIFY COLUMN external_id VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Use raw SQL to revert the column type
        DB::statement('ALTER TABLE categories MODIFY COLUMN external_id VARCHAR(255) NULL');
    }
};
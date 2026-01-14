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
        // First, convert existing data to match new enum values
        DB::statement("UPDATE returns SET return_method = 'SELF_RETURN' WHERE return_method = 'pickup_point'");
        DB::statement("UPDATE returns SET return_method = 'COURIER_RETURN' WHERE return_method = 'courier_pickup'");
        
        // Now update the column definition
        DB::statement("ALTER TABLE returns MODIFY COLUMN return_method ENUM('SELF_RETURN', 'COURIER_RETURN') NULL DEFAULT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert data back to old values
        DB::statement("UPDATE returns SET return_method = 'pickup_point' WHERE return_method = 'SELF_RETURN'");
        DB::statement("UPDATE returns SET return_method = 'courier_pickup' WHERE return_method = 'COURIER_RETURN'");
        
        // Revert to the previous enum values
        DB::statement("ALTER TABLE returns MODIFY COLUMN return_method ENUM('courier_pickup', 'pickup_point') NULL DEFAULT NULL");
    }
};
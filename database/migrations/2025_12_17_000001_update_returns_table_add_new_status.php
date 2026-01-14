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
        // Update the returns table to include the new status in the enum
        DB::statement("ALTER TABLE returns MODIFY COLUMN status ENUM('pending', 'approved', 'in_transit', 'in_transit_back_to_customer', 'received', 'condition_ok', 'condition_bad', 'refund_initiated', 'completed', 'rejected', 'rejected_by_warehouse') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the returns table to the previous enum values
        DB::statement("ALTER TABLE returns MODIFY COLUMN status ENUM('pending', 'approved', 'in_transit', 'received', 'condition_ok', 'condition_bad', 'refund_initiated', 'completed', 'rejected', 'rejected_by_warehouse') DEFAULT 'pending'");
    }
};
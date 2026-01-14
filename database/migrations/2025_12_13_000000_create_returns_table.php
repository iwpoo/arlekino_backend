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
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Customer who initiated return
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade'); // Seller of the product
            $table->enum('status', [
                'pending',           // Return request created, awaiting seller approval
                'approved',          // Seller approved, awaiting item pickup/drop-off
                'in_transit',        // Item is being returned (pickup scheduled/dropped off)
                'in_transit_back_to_customer', // Item is being returned to customer
                'received',          // Item received at warehouse
                'condition_ok',      // Item condition verified, refund processing
                'condition_bad',     // Item damaged/used, return rejected
                'refund_initiated',  // Refund process started
                'completed',         // Return process completed successfully
                'rejected',          // Seller rejected return request
                'rejected_by_warehouse' // Warehouse rejected due to item condition
            ])->default('pending');
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->decimal('logistics_cost', 10, 2)->default(0);
            $table->enum('return_method', ['SELF_RETURN', 'COURIER_RETURN'])->default('COURIER_RETURN');
            $table->string('qr_code')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('seller_id');
            $table->index('status');
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
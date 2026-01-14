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
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('seller_order_id')->nullable()->after('order_id');
            $table->foreign('seller_order_id')->references('id')->on('seller_orders')->nullOnDelete();
            $table->index('seller_order_id');
            $table->index('product_id');
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['seller_order_id']);
            $table->dropColumn('seller_order_id');
        });
    }
};

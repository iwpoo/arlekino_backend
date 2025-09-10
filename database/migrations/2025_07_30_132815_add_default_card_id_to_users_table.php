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
            $table->foreignId('default_card_id')
                ->after('currency')
                ->nullable()
                ->constrained('bank_cards')
                ->onDelete('SET NULL');
            $table->json('authorized_devices')->nullable()->after('default_card_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_card_id']);
            $table->dropColumn('default_card_id');
        });
    }
};

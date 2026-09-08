<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'wallet']);
            $table->index(['user_id', 'category']);
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->index(['user_id', 'is_active']);
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->index(['user_id', 'category']);
        });

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->index(['user_id', 'is_active', 'next_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'date']);
            $table->dropIndex(['user_id', 'wallet']);
            $table->dropIndex(['user_id', 'category']);
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_active']);
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'category']);
        });

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_active', 'next_date']);
        });
    }
};

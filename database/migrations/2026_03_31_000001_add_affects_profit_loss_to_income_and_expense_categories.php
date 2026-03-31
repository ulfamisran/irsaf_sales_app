<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->boolean('affects_profit_loss')->default(true)->after('is_active');
        });

        Schema::table('income_categories', function (Blueprint $table) {
            $table->boolean('affects_profit_loss')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn('affects_profit_loss');
        });

        Schema::table('income_categories', function (Blueprint $table) {
            $table->dropColumn('affects_profit_loss');
        });
    }
};
